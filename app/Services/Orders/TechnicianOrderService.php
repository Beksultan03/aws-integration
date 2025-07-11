<?php

namespace App\Services\Orders;

use App\Http\DTO\Order\OrderConfigDTO;
use App\Models\SbTechnicianOrder;
use App\Repositories\ArtSkuListRepository;
use App\Repositories\ProductRepository;
use App\Resources\OrderConfigResource;
use App\Services\Components\ComponentService;
use App\Services\Kits\KitService;
use App\Services\Parts\PartService;
use App\Services\QualityCheck\QcDataService;
use App\Services\SerialNumber\SerialNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

readonly class TechnicianOrderService
{

    public function __construct(
        private PartService $partService,
        private ComponentService  $componentService,
        private KitService  $kitService,
        private ProductRepository $productRepository,
        private QcDataService $qcDataService,
        private SerialNumberService $serialNumberService,
        private ArtSkuListRepository $artSkuListRepository,
    )
    {
    }

    public function getProcessingOrderConfig(): Collection
    {
        $techOrders = SbTechnicianOrder::query()
            ->from('tbl_sb_technician_orders as tech')
            ->select('tech.id', 'tech.order_id', 'i.productid as sku', 'tech.serial_number',
                'tech.page_number', 'tech.technician_id', 'tech.cdt_serial',
                'tech.is_PU_order','tech.optional_fans' ,'i.displayname as display_title', 'i.orderitemid as order_item_id', 'i.qty')
            ->leftJoin('tbl_sb_order_items as i', function ($join) {
                $join->on('i.orderid', '=', 'tech.order_id')
                    ->whereRaw("i.productid LIKE CONCAT('%', tech.sku, '%')");
            })
            ->where('tech.current_processing', 1)
            ->where('tech.status', '0')
            ->whereNotNull('i.orderid')
            ->get();

        if (empty($techOrders)) {
            return collect();
        }

        return $techOrders->map(function ($order) {
            $orderConfig = new OrderConfigDTO();
            $orderConfig->buildFromArray($order->toArray());

            $orderConfig->order_id = $this->getOrderNumber($order);

            $components = $this->componentService->getProcessingData($order->order_id, $order->order_item_id);

            $optionalSerialNumbers = explode(",", $order->optional_fans);

            $componentSerialNumbers = $this->serialNumberService->getWithSku($optionalSerialNumbers);

            $orderConfig->details = $components->map(function ($component) use($order, $componentSerialNumbers) {
                $title = $component->display_title ?? $component->name;

                if (empty($title) && $this->isArtSku($component->sku)){
                    $title = $this->artSkuListRepository->getDescriptionBySku($component->sku);
                }
                return [
                    'title' =>$title,
                    'sku' => $component->sku,
                    'qty' => $component->qty/$order->qty,
                    'serial_number' => $componentSerialNumbers[$component->sku] ?? null,
                ];
            })->toArray();

            $productSummary = [
                'raid' => $this->qcDataService->getRaidByOrderId($order->order_id),
                'ram' => null,
                'gpu' => null,
                'os' => null,
                'cpu' => null,
            ];


            if ($this->isCDTSku($order->sku)){
                $productDetails = $this->partService->getPartDetailsByСomponents($components, $order->qty);
            }
            else{
                $productDetails = $this->kitService->getProductDetailsBySku($order->sku);
            }

            if (empty($productDetails)) {
                $productDetails = $this->productRepository->getDetailsBySku($order->sku);
            }

            $orderConfig->summary = array_merge($productSummary, $this->mapSummary($productDetails ?? []));


            return new OrderConfigResource($orderConfig);
        });
    }

    private function getOrderNumber(Model $order): ?string
    {
        return $order->is_PU_order
            ? "PU" . sprintf("%'.05d", $order->order_id)
            : $order->order_id;
    }


    public function getOrderBySerialNumber(string $serialNumber): ?Model
    {
        $order = SbTechnicianOrder::query()
            ->from('tbl_sb_technician_orders as tech')
            ->select('tech.order_id','tech.is_PU_order', 'i.orderitemid as order_item_id', 'i.qty', 'tech.technician_id')
            ->leftJoin('tbl_sb_order_items as i', function ($join) {
                $join->on('i.orderid', '=', 'tech.order_id')
                    ->whereRaw("i.productid LIKE CONCAT('%', tech.sku, '%')");
            })
            ->where('serial_number', $serialNumber)
            ->whereNotNull('i.orderid')
            ->orderBy('tech.order_id', 'desc')
            ->first();

        if (!$order) {
            $order = SbTechnicianOrder::query()
                ->from('tbl_sb_technician_orders as tech')
                ->select('tech.order_id','tech.is_PU_order', 'h.orderitemid as order_item_id', 'h.qty', 'tech.technician_id')
                ->leftJoin('tbl_sb_history_order_item as h', function ($join) {
                    $join->on('h.orderid', '=', 'tech.order_id')
                        ->whereRaw("h.sku LIKE CONCAT('%', tech.sku, '%')");
                })
                ->where('serial_number', $serialNumber)
                ->whereNotNull('h.orderid')
                ->orderBy('tech.order_id', 'desc')
                ->first();
        }

        return $order;
    }
    public function getOrderId(string $serialNumber): ?SbTechnicianOrder
    {
        return SbTechnicianOrder::query()
            ->select('order_id', 'is_PU_order')
            ->where('serial_number', $serialNumber)
            ->orderBy('order_id', 'desc')
            ->first();
    }

    public function isCDTSku(string $sku): bool
    {
        return str_contains($sku, 'CreateCDT') ||
            str_contains($sku, '-CDT-');
    }

    public function isArtSku(string $sku): bool
    {
        return str_contains($sku, '-ART-');
    }

    function mapSummary(array $items): array
    {
        $result = [];
        $storageCounter = 1;

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

    public function getTecnicianIdByOrderId(int $orderId): ?int
    {
        return SbTechnicianOrder::query()
            ->select('technician_id')
            ->where('order_id', $orderId)
            ->first()?->technician_id;
    }
}
