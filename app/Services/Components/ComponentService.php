<?php

namespace App\Services\Components;

use App\Models\SbHistoryOrderItemComponent;
use App\Models\SbOrderItemComponent;
use App\Models\WindowsKey;
use App\Services\Kits\KitService;
use App\Services\Parts\PartService;
use Illuminate\Database\Eloquent\Collection;

class ComponentService
{
    private PartService $partService;
    private KitService $kitService;

    public function __construct(PartService $partService, KitService $kitService)
    {
        $this->partService = $partService;
        $this->kitService = $kitService;
    }

    public function getProcessingData(string $orderId, string $orderItemId): Collection
    {
        return SbOrderItemComponent::query()
            ->select(
                'c.productid as sku',
                'c.totalqty as qty',
                'b.display_title',
                'p.name',
                'p.part_type_id'
            )
            ->from('tbl_sb_order_item_components as c')
            ->leftJoin('tbl_base_product as b', 'b.sku', '=', 'c.productid')
            ->leftJoin('tbl_parts as p', 'p.sku', '=', 'c.productid')
            ->where('c.orderitemid', $orderItemId)
            ->where('c.orderid', $orderId)
            ->orderBy('c.id')
            ->get();
    }
    public function getData(string $orderId, string $orderItemId): Collection
    {
        $data = SbOrderItemComponent::query()
            ->select(
                'c.productid as sku',
                'c.totalqty as qty',
                'b.display_title',
                'p.name',
                'p.part_type_id'
            )
            ->from('tbl_sb_order_item_components as c')
            ->leftJoin('tbl_base_product as b', 'b.sku', '=', 'c.productid')
            ->leftJoin('tbl_parts as p', 'p.sku', '=', 'c.productid')
            ->where('c.orderitemid', $orderItemId)
            ->where('c.orderid', $orderId)
            ->orderBy('c.id')
            ->get();

        if ($data->isEmpty()) {
            $data = SbHistoryOrderItemComponent::query()
                ->select(
                    'h.productid as sku',
                    'h.totalqty as qty',
                    'b.display_title',
                    'p.name',
                    'p.part_type_id'
                )
                ->from('tbl_sb_history_order_item_components as h')
                ->leftJoin('tbl_base_product as b', 'b.sku', '=', 'h.productid')
                ->leftJoin('tbl_parts as p', 'p.sku', '=', 'h.productid')
                ->where('h.orderitemid', $orderItemId)
                ->where('h.orderid', $orderId)
                ->orderBy('h.id')
                ->get();
        }

        return $data;
    }
    public function getCDTDetailsFromComponents(string $orderId): ?array
    {
        $orderComponents = SbOrderItemComponent::query()
            ->select('productid as sku')
            ->where('orderid', $orderId)
            ->get();

        if ($orderComponents->isEmpty()) {
            $orderComponents = SbHistoryOrderItemComponent::query()
                ->select('productid as sku')
                ->where('orderid', $orderId)
                ->get();
        }

        if ($orderComponents->isEmpty()) {
            return null;
        }

        $partDetails = [];

        foreach ($orderComponents as $orderComponent) {
            $partDetail = $this->partService->checkPartType($orderComponent->sku);
            $partDetails = array_merge($partDetails, $partDetail);
        }

        return [
            'os' => $partDetails['os'] ?? null,
        ];
    }

    public function getAllSkuByOrderId(string $orderId): array
    {
        $orderComponents = SbOrderItemComponent::query()
            ->where('orderid', $orderId)
            ->pluck('productid');

        if ($orderComponents->isEmpty()) {
            $orderComponents = SbHistoryOrderItemComponent::query()
                ->where('orderid', $orderId)
                ->pluck('productid');
        }

        return $orderComponents->toArray();
    }
    public function getProductDetails(?string $sku): array
    {
        return $this->kitService->getDetailsFromKit($sku);
    }

    public function getQuantityKeyTypeByOrderId(int $orderId, int $orderItemId): \Illuminate\Support\Collection
    {
        $results = SbOrderItemComponent::query()
            ->select('productid', 'totalqty')
            ->where('orderitemid', $orderItemId)
            ->where('orderid', $orderId)
            ->where('productid', 'LIKE', WindowsKey::PRODUCT_PATTERN)
            ->unionAll(
                SbHistoryOrderItemComponent::query()
                    ->select('productid', 'totalqty')
                    ->where('orderitemid', $orderItemId)
                    ->where('orderid', $orderId)
                    ->where('productid', 'LIKE', WindowsKey::PRODUCT_PATTERN)
            )
            ->get();

        return $results;
    }

    public function getOSByOrderItemId($orderItemId, $orderId)
    {
        return SbOrderItemComponent::query()
            ->from('tbl_sb_order_item_components as c')
            ->where('c.orderitemid', $orderItemId)
            ->where('c.orderid', $orderId)
            ->where('productid', 'LIKE', WindowsKey::PRODUCT_PATTERN)
            ->orderBy('c.id')
            ->pluck('productid')
            ->first();
    }

}
