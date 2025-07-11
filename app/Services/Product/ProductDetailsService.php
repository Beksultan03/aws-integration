<?php

namespace App\Services\Product;

use App\Exceptions\ProductDetailsException;
use App\Http\DTO\Product\ProductDetailsDTO;
use App\Repositories\ProductRepository;
use App\Resources\ProductDetailsResource;
use App\Services\Components\ComponentService;
use App\Services\Kits\KitService;
use App\Services\OrderItems\OrderItemService;
use App\Services\Orders\TechnicianOrderService;
use App\Services\Parts\PartService;
use App\Services\QualityCheck\QcDataService;
use App\Services\SerialNumber\SerialNumberService;
use Illuminate\Database\Eloquent\Model;

readonly class ProductDetailsService
{
    public function __construct(
        private TechnicianOrderService $technicianOrderService,
        private SerialNumberService $serialNumberService,
        private OrderItemService $orderItemService,
        private QcDataService $qcDataService,
        private ComponentService $componentService,
        private PartService $partService,
        private KitService $kitService,
        private ProductRepository $productRepository,
    )
    {}

    /**
     * @throws ProductDetailsException
     */
    public function getProductDetailsBySerialNumber(string $serialNumber): ProductDetailsResource
    {
        $serialNumberValue = $this->serialNumberService->getSerialNumberValue($serialNumber);
        if (!$serialNumberValue) {
            throw ProductDetailsException::serialNumberNotFound();
        }

        $productSummary = [
            'serial_number' => $serialNumber,
            'order_id' => null,
            'raid' => null,
            'display_title' => null,
            'ram' => null,
            'gpu' => null,
            'os' => null,
            'cpu' => null,
        ];

        $order = $this->getOrderIdBySerialNumber($serialNumber);

        if ($order){
            $productSummary['order_id'] = $this->getOrderNumber($order);
            $productSummary['raid'] = $this->qcDataService->getRaidByOrderId($order->order_id);

            $sku = $this->orderItemService->getOrderSku($order->order_id);

            $productDetails = [];

            if ($sku) {
                if (str_contains($sku, "CreateCDT")){
                    $components = $this->componentService->getData($order->order_id, $order->order_item_id);
                    $productDetails = $this->partService->getPartDetailsByСomponents($components, $order->qty);
                }
                else{
                    $productDetails = $this->kitService->getProductDetailsBySku($sku);
                }
            }
        }

        if (empty($productDetails)) {
            $productDetails = $this->productRepository->getDetailsById($serialNumberValue->product_id);
        }
        return new ProductDetailsResource(array_merge($productSummary, $this->mapSummary($productDetails ?? [])));
    }

    private function getOrderNumber(Model $order): ?string
    {
        return $order->is_PU_order
            ? "PU" . sprintf("%'.05d", $order->order_id)
            : $order->order_id;
    }

    private function getOrderIdBySerialNumber(string $serialNumber): ?Model
    {
        return $this->technicianOrderService->getOrderBySerialNumber($serialNumber);
    }

    function mapSummary(array $items): array
    {
        $result = [];

        if (!empty($items['storage'])) {
            $storageSummary = $this->sumStorageGigabytes(explode('+', $items['storage']));

            if ($storageSummary['ssd'] > 0) {
                $result['main_storage'] = [
                    'value' => (string)$storageSummary['ssd'],
                    'type' => 'ssd',
                ];
            }

            if ($storageSummary['hdd'] > 0) {
                if (!isset($result['main_storage'])) {
                    $result['main_storage'] = [
                        'value' => (string)$storageSummary['hdd'],
                        'type' => 'hdd',
                    ];
                } else {
                    $result['additional_storage'] = [
                        'value' => (string)$storageSummary['hdd'],
                        'type' => 'hdd',
                    ];
                }
            }
        }

        foreach ($items as $key => $value) {
            if ($key !== 'storage') {
                $result[$key] = $value;
            }
        }

        return $result;
    }


    public function sumStorageGigabytes(array $storageItems): array
    {
        $totalSSD = 0;
        $totalHDD = 0;

        foreach ($storageItems as $storageItem) {
            $individualItems = explode('+', $storageItem);

            foreach ($individualItems as $item) {
                $item = strtolower(trim($item));

                if (stripos($item, 'ssd') !== false) {
                    $totalSSD += $this->extractStorageValue($item);
                } elseif (stripos($item, 'hdd') !== false) {
                    $totalHDD += $this->extractStorageValue($item);
                }
            }
        }

        return [
            'ssd' => $totalSSD,
            'hdd' => $totalHDD
        ];
    }

    public function extractStorageValue(string $storageValue): int
    {
        if (preg_match('/([\d.]+)\s*(tb|gb)/i', $storageValue, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);

            if ($unit === 'tb') {
                return $value * 1024;
            }

            return $value;
        }

        return 0;
    }
}
