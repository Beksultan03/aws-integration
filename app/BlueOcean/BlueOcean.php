<?php

namespace App\BlueOcean;

use App\BlueOcean\Exceptions\ApiException;
use App\BlueOcean\Helper\CurlHelper;
use App\BlueOcean\Mapper\Compare;
use App\BlueOcean\Mapper\Mapper;
use App\BlueOcean\Models\Inventory;
use App\Models\BoOrderApiLog;
use App\Models\Kit;
use App\Models\SbOrder;
use App\Models\SbOrderItem;
use App\Models\SbOrderItemComponent;
use App\Repositories\KitRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BlueOcean
{
    public const int RELEASE_ORDERS = 1010;
    public const int RETRIEVE_ITEMS = 1012;
    public const int HIDE_ORDERS = 1013;
    public const int SEARCH_INVENTORY = 1018;
    public const int CREATE_CUSTOM_UPC = 1011;

    public const array SUPPORTED_SOURCES = [
        'amazon',
        'walmart'
    ];

    /**
     * @throws ApiException
     */
    public function setOrdersAsBlueOcean(array $ids): void
    {
        /** @var SbOrder[] $orders */
        $orders = SbOrder::getByOrderIds($ids);
        foreach ($orders as $order) {
            $this->checkIfSourceSupported($order);
        }

        SbOrder::updateOrderStatus(
            $ids,
            [
                'sent_to_BO' => 1,
                'previous_kit_status' => DB::raw('kit_status'),
                'kit_status' => Kit::KIT_STATUS_BLUE_OCEAN,
            ]
        );
    }

    /**
     * @throws ApiException|Throwable
     */
    public function releaseOrders(array $ids): ?array
    {
        return DB::transaction(function () use ($ids) {
            /** @var SbOrder[] $orders */
            $orders = SbOrder::getByOrderIds($ids);
            $unmatchedSKU = Inventory::getItemsWithDifferenceInSkuAndUpc()->pluck('upc', 'sku');
            $mappedProducts = ['order_array' => [],];
            $orderSelectFields = ['orderid', 'orderitemid', 'productid', 'qty', 'item_type', 'order_source_seller_sku'];
            $orderComponentsSelectFields = ['id', 'orderid', 'orderitemid', 'totalqty', 'productid', 'is_main_item', 'kititemid'];
            foreach ($orders as $order) {
                $this->checkIfSourceSupported($order);
                $orderItems = SbOrderItem::getByOrderId($order->orderid, $orderSelectFields);
                $components = SbOrderItemComponent::getByOrderId($order->orderid, $orderComponentsSelectFields);
                $products = [];
                foreach ($orderItems as $item) {
                    $mainComponent = null;
                    $parts = [];
                    $bundles = [];
                    /** @var SbOrderItemComponent[]|Collection $itemComponents */
                    $itemComponents = $components->filter(
                        fn(SbOrderItemComponent $component) => ($item->orderitemid === $component->orderitemid)
                    );

                    foreach ($itemComponents as $component) {
                        if ($component->isPart()) {
                            $parts[] = $component;
                            continue;
                        }

                        if ($component->isMain()) {
                            $mainComponent = $component;
                            continue;
                        }

                        $bundles[] = $component;
                    }

                    // Temporary solution to avoid RAID
                    if (str_contains($item->productid, 'RD-')) {
                        return $this->raidMessage();
                    }
                    $assembleItems = [];
                    if ($parts ?? false) {
                        foreach ($parts as $part) {
                            if (str_starts_with($part->productid, 'RD-')) {
                                return $this->raidMessage();
                            }
                        }
                    }

                    if ($item->isKit()) {
                        /** @var KitRepository $kitRepository */
                        $kitRepository = app(KitRepository::class);
                        $kit = $kitRepository->getByBundleSku($item->productid);
                        $comparison = new Compare($kit->product->getSpecification(), $kit->getSpecification());
                        $assembleItems = (new Mapper())->map($comparison);
                    }

                    if ($item->isAccessory() && $itemComponents->isEmpty()) {
                        for ($i = 0; $i < $item->qty; $i++) {
                            $products[] = [
                                'sku' => $item->sku,
                                'upc' => $item->productid,
                                'seller_cloud_order_item_id' => $item->orderitemid,
                            ];
                        }
                    }

                    $mainComponentTotalQuantity = $mainComponent->totalqty ?? 0;
                    for ($i = 0; $i < $mainComponentTotalQuantity; $i++) {
                        $currentSKU = $mainComponent->blueOceanSKU;
                        $currentUPC = $unmatchedSKU[$currentSKU] ?? null;
                        $product = [
                            'sku' => $item->sku,
                            'upc' => $currentUPC ?? $currentSKU,
                            'bundle_array' => [],
                            'assemble_items' => $assembleItems,
                            'seller_cloud_order_item_id' => $item->orderitemid,
                            'seller_cloud_order_kit_item_id' => $mainComponent->kititemid,
                        ];

                        foreach ($bundles as $bundle) {
                            $product['bundle_array'][] = [
                                'upc' => $bundle->blueOceanSKU,
                                'quantity' => 1,
                            ];
                        }

                        $products[] = $product;
                    }

                }

                $mappedOrder = [
                    'channel' => $order->source,
                    'order_id' => $order->ordersourceorderid,
                    'product_array' => $products,
                    'seller_cloud_order_id' => $order->orderid,
                ];
                $mappedProducts['order_array'][] = $mappedOrder;
            }

            $mappedProducts = $this->handleProductsIfExistsOnBlueOcean($mappedProducts);

            SbOrder::updateOrderStatus(
                $ids,
                [
                    'sent_to_BO' => 1,
                    'previous_kit_status' => DB::raw('kit_status'),
                    'kit_status' => Kit::KIT_STATUS_BLUE_OCEAN,
                ]
            );

            $response = CurlHelper::exec(self::RELEASE_ORDERS, $mappedProducts);
            BoOrderApiLog::write(self::RELEASE_ORDERS, $ids, $mappedProducts, $response);

            return $response;
        });
    }

    /**
     * @param array $orders
     * @return array
     */
    protected function handleProductsIfExistsOnBlueOcean(array $orders): array
    {
        foreach ($orders as $orderIdx => $order) {
            foreach ($order as $productsIdx => $products) {
                if ($products['product_array'] ?? false) {
                    foreach ($products['product_array'] as $productIdx => $product) {
                        if (SbOrder::isSkuExistsInBlueOceanOrdersHistory($product['sku'])) {
                            $element = &$orders[$orderIdx][$productsIdx]['product_array'][$productIdx];
                            if (isset($product['assemble_items'])) {
                                unset($element['assemble_items']);
                            }
                        }
                    }
                }
            }
        }

        return $orders;
    }

    /**
     * @param array $ids
     * @return array|null
     */
    public function hideOrders(array $ids): ?array
    {
        return DB::transaction(function () use ($ids) {
            SbOrder::updateOrderStatus($ids, [
                'sent_to_BO' => 0,
                'kit_status' => DB::raw('previous_kit_status'),
                'previous_kit_status' => null,
            ]);

            $orders = SbOrder::query()
                ->select('ordersourceorderid', 'ordersource')
                ->where('orderid', $ids)
                ->get()
                ->map(fn(SbOrder $order) => [
                    'channel' => $order->source,
                    'order_id' => $order->ordersourceorderid,
                ])->toArray();


            $response = CurlHelper::exec(self::HIDE_ORDERS, [
                'order_array' => $orders,
            ]);
            BoOrderApiLog::write(self::RELEASE_ORDERS, $ids, $orders, $response);

            return $response;
        });
    }

    /**
     * All possible parts that exists
     *
     * @return array|null
     * @throws Throwable
     */
    public function retrieveItems(): ?array
    {
        return CurlHelper::exec(self::RETRIEVE_ITEMS, []);
    }

    /**
     * All possible parts that exists
     *
     * @param array $data
     * @return array|null
     * @throws Throwable
     */
    public function createCustomUpc(array $data): ?array
    {
        return CurlHelper::exec(self::CREATE_CUSTOM_UPC, $data);
    }

    /**
     * @throws Throwable
     * @throws ApiException
     */
    public function searchInventory(): array
    {
        $conditions = ['search' => '',];

        return CurlHelper::execWithPaging(
            self::SEARCH_INVENTORY,
            $conditions
        );
    }

    /**
     * @throws ApiException
     */
    public function checkIfSourceSupported(SbOrder $order): void
    {
        if (!in_array($order->source, self::SUPPORTED_SOURCES)) {
            throw new ApiException('Unknown source: ' . $order->source);
        }
    }

    protected function raidMessage(): array
    {
        $response = [];
        $response['customMessage'] = "RAID order. Please, send it manually.";
        Log::channel('blue-ocean')
            ->error(
                "[execute] {$response['customMessage']}",
                ['time' => now()]
            );

        return $response;
    }

}
