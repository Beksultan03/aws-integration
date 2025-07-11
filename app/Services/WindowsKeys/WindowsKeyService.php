<?php

namespace App\Services\WindowsKeys;

use App\BlueOcean\Exceptions\ApiException;
use App\Event\WindowsKeys\KeyRetrievedEvent;
use App\Event\WindowsKeys\KeysRetrievedEvent;
use App\Event\WindowsKeys\RefundKeyEvent;
use App\Event\WindowsKeys\RefundKeysEvent;
use App\Event\WindowsKeys\SendToManufacturerEvent;
use App\Event\WindowsKeys\UpdateStatusEvent;
use App\Exports\WindowsKeysExport;
use App\Http\DTO\WindowsKeys\HandleRMAErrorDTO;
use App\Http\DTO\WindowsKeys\RetrieveByOrderIdDTO;
use App\Http\DTO\WindowsKeys\RetrieveByQuantityDTO;
use App\Http\DTO\WindowsKeys\RetrievedDTO;
use App\Http\DTO\WindowsKeys\SendToManufacturerDTO;
use App\Http\DTO\WindowsKeys\UpdateDTO;
use App\Http\DTO\WindowsKeys\UpdateStatusDTO;
use App\Http\DTO\WindowsKeys\UploadDTO;
use App\Imports\WindowsKeysImport;
use App\Models\OrderWindowsKeys;
use App\Models\WindowsKey;
use App\Services\Components\ComponentService;
use App\Services\OrderItems\OrderItemService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TechnicianOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class WindowsKeyService
{

    private OrderItemService $orderItemService;
    private OrderService $orderService;
    private TechnicianOrderService $technicianOrderService;
    private ComponentService $componentService;

    public function __construct(OrderItemService $orderItemService, ComponentService $componentService, OrderService $orderService, TechnicianOrderService $technicianOrderService)
    {
        $this->orderItemService = $orderItemService;
        $this->componentService = $componentService;
        $this->orderService = $orderService;
        $this->technicianOrderService = $technicianOrderService;
    }

    public function getFilteredKeys(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = WindowsKey::query();

        if (isset($filters['created_date_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_date_from']);
        }

        if (isset($filters['created_date_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_date_to']);
        }

        if (isset($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (isset($filters['vendor'])) {
            $query->where('vendor', $filters['vendor']);
        }

        if (isset($filters['key_type'])) {
            $query->where('key_type', $filters['key_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['serial_key'])) {
            $query->where('serial_key', $filters['serial_key']);
        }

        return $query->orderBy('updated_at', 'desc');
    }

    public function decryptKeys($windowsKeys)
    {
        $resultedWindowsKeys = $windowsKeys->toArray();
        if (empty($resultedWindowsKeys)) {
            return throw new ApiException('There are no keys with this order ID');
        }
        foreach ($resultedWindowsKeys as &$record) {
            $record['key'] = decrypt($record['key']);
        }

        return $resultedWindowsKeys;
    }

    public function importKeys(UploadDTO $uploadDTO): array
    {
        $filePath = $this->preprocessExcelFile($uploadDTO->file);

        Excel::import((new WindowsKeysImport($uploadDTO->user_id)), $filePath);

        return [
            'status' => 'success',
            'message' => 'Keys imported successfully!',
        ];
    }
    private function preprocessExcelFile($file): string
    {
        $odsFilePath = $file->storeAs('temp', $file->getClientOriginalName());
        $spreadsheet = IOFactory::load(Storage::path($odsFilePath));

        $sheet = $spreadsheet->getActiveSheet();

        $cleanedSpreadsheet = new Spreadsheet();
        $cleanedSheet = $cleanedSpreadsheet->getActiveSheet();

        $rows = [];
        $rowIndex = 1;
        $isFirstRow = true;
        foreach ($sheet->getRowIterator() as $row) {

            $rowData = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(TRUE);
            if ($isFirstRow) {
                $isFirstRow = false;
                $rowData = array_map(fn($cell) => $cell->getValue(), iterator_to_array($row->getCellIterator()));
                $cleanedSheet->fromArray($rowData, null, "A$rowIndex");
                $rowIndex++;
                continue;
            }
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            $rows[] = $rowData;
        }
        $cleanedSheet->fromArray($rows, null, "A$rowIndex");

        $xlsxFileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.xlsx';
        $xlsxFilePath = storage_path('app/private/temp/' . $xlsxFileName);

        $writer = IOFactory::createWriter($cleanedSpreadsheet, 'Xlsx');
        $writer->save($xlsxFilePath);


        return $xlsxFilePath;
    }

    /**
     * @throws \Exception
     */
    public function getDecryptedKey(RetrievedDTO $retrievedDTO): ?string
    {

        $keyType = $this->getKeyTypeBySerialNumber($retrievedDTO);

        $windowsKey = WindowsKey::query()->select('id', 'key')
            ->where('key_type', $keyType)
            ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
            ->whereNull('order_id')
            ->whereNull('serial_key')
            ->first();
        $retrievedDTO->entity_id = $windowsKey->id;

        if (!$windowsKey) {
            return throw new ApiException('No key found for this Key Type!');
        }

        try {
            $decryptedKey = decrypt($windowsKey->key);

            $windowsKey->order_id = $retrievedDTO->order_id;
            $windowsKey->serial_key = $retrievedDTO->serial_key;
            $windowsKey->status = WindowsKey::KEY_TYPE_USED;
            $windowsKey->save();

            $retrievedDTO->entity_id = $windowsKey->id;
        } catch (\Exception $e) {
            throw new \Exception("Error decrypting the key");
        }
        event(new KeyRetrievedEvent($retrievedDTO));

        return $decryptedKey;
    }

    public function sendToManufacturer(SendToManufacturerDTO $sendToManufacturerDTO): array
    {

        $keys = WindowsKey::query()
            ->select('id', 'status')
            ->whereIn('id', $sendToManufacturerDTO->ids)
            ->get();

        $groupedKeys = $keys->groupBy('status');

        $keysToSend = $keys->filter(function ($key) {
            return $key->status === WindowsKey::KEY_TYPE_RMA_NEEDED || $key->status === WindowsKey::KEY_TYPE_REFUND;
        })->pluck('id')->all();
        $alreadySentKeys = $groupedKeys->get(WindowsKey::KEY_TYPE_SENT_TO_MANUFACTURER, collect())->pluck('id')->all();
        $notUsedKeys = $groupedKeys->get(WindowsKey::KEY_TYPE_NOT_USED, collect())->pluck('id')->all();
        WindowsKey::query()->whereIn('id', $keysToSend)->update([
            'status' => WindowsKey::KEY_TYPE_SENT_TO_MANUFACTURER,
        ]);

        $sendToManufacturerDTO->keys_to_send = $keysToSend;

        event(new SendToManufacturerEvent($sendToManufacturerDTO));

        return [
            'data' => [
                'alreadySentKeys' => $alreadySentKeys,
                'notUsedKeys' => $notUsedKeys,
            ],
            'status' => 'success',
            'message' => 'Keys sent to manufacturer successfully!',
        ];
    }


    public function getAnalyticsData(): array
    {
        $now = now();
        $sub30Days = $now->copy()->subDays(30);
        $sub14Days = $now->copy()->subDays(14);

        $usedKeysAggregation = WindowsKey::query()
            ->select(
                DB::raw('COUNT(*) as total_keys_used'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_RMA_NEEDED . '" THEN 1 ELSE 0 END) as total_keys_rma_needed'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_TRANSFERRED . '" THEN 1 ELSE 0 END) as total_keys_transferred'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_DOWNLOADED . '" THEN 1 ELSE 0 END) as total_keys_downloaded'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_RMA_NEEDED . '" AND key_type = "' . WindowsKey::TYPE_PRO . '" THEN 1 ELSE 0 END) as total_keys_rma_needed_pro'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_RMA_NEEDED . '" AND key_type = "' . WindowsKey::TYPE_HOME . '" THEN 1 ELSE 0 END) as total_keys_rma_needed_home'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_TRANSFERRED . '" AND key_type = "' . WindowsKey::TYPE_PRO . '" THEN 1 ELSE 0 END) as total_keys_transferred_pro'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_TRANSFERRED . '" AND key_type = "' . WindowsKey::TYPE_HOME . '" THEN 1 ELSE 0 END) as total_keys_transferred_home'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_DOWNLOADED . '" AND key_type = "' . WindowsKey::TYPE_PRO . '" THEN 1 ELSE 0 END) as total_keys_downloaded_pro'),
                DB::raw('SUM(CASE WHEN status = "' . WindowsKey::KEY_TYPE_DOWNLOADED . '" AND key_type = "' . WindowsKey::TYPE_HOME . '" THEN 1 ELSE 0 END) as total_keys_downloaded_home'),
                DB::raw('SUM(CASE WHEN key_type = "' . WindowsKey::TYPE_PRO . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as total_keys_used_pro'),
                DB::raw('SUM(CASE WHEN key_type = "' . WindowsKey::TYPE_HOME . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as total_keys_used_home'),
                DB::raw('SUM(CASE WHEN updated_at >= "' . $sub14Days->toDateTimeString() . '" AND key_type = "' . WindowsKey::TYPE_PRO . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as used_last_14_days_pro'),
                DB::raw('SUM(CASE WHEN updated_at >= "' . $sub30Days->toDateTimeString() . '" AND key_type = "' . WindowsKey::TYPE_PRO . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as used_last_30_days_pro'),
                DB::raw('SUM(CASE WHEN updated_at >= "' . $sub14Days->toDateTimeString() . '" AND key_type = "' . WindowsKey::TYPE_HOME . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as used_last_14_days_home'),
                DB::raw('SUM(CASE WHEN updated_at >= "' . $sub30Days->toDateTimeString() . '" AND key_type = "' . WindowsKey::TYPE_HOME . '" AND status = "' . WindowsKey::KEY_TYPE_USED . '" THEN 1 ELSE 0 END) as used_last_30_days_home'),
            )
            ->where(function ($query) {
                $query->where('status', WindowsKey::KEY_TYPE_USED)
                    ->orWhere('status', WindowsKey::KEY_TYPE_RMA_NEEDED)
                    ->orWhere('status', WindowsKey::KEY_TYPE_SENT_TO_MANUFACTURER)
                    ->orWhere('status', WindowsKey::KEY_TYPE_TRANSFERRED)
                    ->orWhere('status', WindowsKey::KEY_TYPE_DOWNLOADED);
            })
            ->first()
            ->toArray();

        $usedKeysAggregation = array_map(function ($value) {
            return $value !== null ? $value : 0;
        }, $usedKeysAggregation);

        $keysInStockByType = WindowsKey::query()
            ->select('key_type', DB::raw('COUNT(*) as total'))
            ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
            ->groupBy('key_type')
            ->get()
            ->pluck('total', 'key_type')
            ->toArray();

        $keysInStockByType += ['Pro' => 0, 'Home' => 0];

        $totalKeysInStock = array_sum($keysInStockByType);

        $runRate30DaysByType = [
            'Pro' => round($usedKeysAggregation['used_last_30_days_pro'] / 30, 2),
            'Home' => round($usedKeysAggregation['used_last_30_days_home'] / 30, 2),
        ];

        $runRate14DaysByType = [
            'Pro' => round($usedKeysAggregation['used_last_14_days_pro'] / 14, 2),
            'Home' => round($usedKeysAggregation['used_last_14_days_home'] / 14, 2),
        ];

        $runRate30Days = array_sum($runRate30DaysByType);
        $runRate14Days = array_sum($runRate14DaysByType);

        return [
            'Total' => [
                'keys_in_stock' => $totalKeysInStock,
                'keys_used' => $usedKeysAggregation['total_keys_used_pro'] + $usedKeysAggregation['total_keys_used_home'],
                'keys_rma_needed' => $usedKeysAggregation['total_keys_rma_needed'],
                'keys_transferred' => $usedKeysAggregation['total_keys_transferred'],
                'keys_downloaded' => $usedKeysAggregation['total_keys_downloaded'],
                'run_rate_14_days' => $runRate14Days,
                'run_rate_30_days' => $runRate30Days,
                'used_last_30_days' => $usedKeysAggregation['used_last_30_days_pro'] + $usedKeysAggregation['used_last_30_days_home'],
                'used_last_14_days' => $usedKeysAggregation['used_last_14_days_pro'] + $usedKeysAggregation['used_last_14_days_home'],
                'days_left_inventory_14_days' => $runRate14Days ? ($totalKeysInStock > 0 ? round($totalKeysInStock / $runRate14Days) : 0) : 'Keys not used in last 14 days',
                'days_left_inventory_30_days' => $runRate30Days ? ($totalKeysInStock > 0 ? round($totalKeysInStock / $runRate30Days) : 0) : 'Keys not used in last 30 days',
            ],
            'Home' => [
                'keys_in_stock' => $keysInStockByType['Home'],
                'keys_used' => $usedKeysAggregation['total_keys_used_home'],
                'keys_rma_needed' => $usedKeysAggregation['total_keys_rma_needed_home'],
                'keys_transferred' => $usedKeysAggregation['total_keys_transferred_home'],
                'keys_downloaded' => $usedKeysAggregation['total_keys_downloaded_home'],
                'run_rate_14_days' => $runRate14DaysByType['Home'],
                'run_rate_30_days' => $runRate30DaysByType['Home'],
                'used_last_30_days' => $usedKeysAggregation['used_last_30_days_home'],
                'used_last_14_days' => $usedKeysAggregation['used_last_14_days_home'],
                'days_left_inventory_14_days' => $runRate14DaysByType['Home'] ? ($keysInStockByType['Home'] > 0 ? round($keysInStockByType['Home'] / $runRate14DaysByType['Home']) : 0) : 'Keys not used in last 14 days',
                'days_left_inventory_30_days' => $runRate30DaysByType['Home'] ? ($keysInStockByType['Home'] > 0 ? round($keysInStockByType['Home'] / $runRate30DaysByType['Home']) : 0) : 'Keys not used in last 30 days',
            ],
            'Pro' => [
                'keys_in_stock' => $keysInStockByType['Pro'],
                'keys_used' => $usedKeysAggregation['total_keys_used_pro'],
                'keys_rma_needed' => $usedKeysAggregation['total_keys_rma_needed_pro'],
                'keys_transferred' => $usedKeysAggregation['total_keys_transferred_pro'],
                'keys_downloaded' => $usedKeysAggregation['total_keys_downloaded_pro'],
                'run_rate_14_days' => $runRate14DaysByType['Pro'],
                'run_rate_30_days' => $runRate30DaysByType['Pro'],
                'used_last_30_days' => $usedKeysAggregation['used_last_30_days_pro'],
                'used_last_14_days' => $usedKeysAggregation['used_last_14_days_pro'],
                'days_left_inventory_14_days' => $runRate14DaysByType['Pro'] ? ($keysInStockByType['Pro'] > 0 ? round($keysInStockByType['Pro'] / $runRate14DaysByType['Pro']) : 0) : 'Keys not used in last 14 days',
                'days_left_inventory_30_days' => $runRate30DaysByType['Pro'] ? ($keysInStockByType['Pro'] > 0 ? round($keysInStockByType['Pro'] / $runRate30DaysByType['Pro']) : 0) : 'Keys not used in last 30 days',
            ],
        ];
    }

    /**
     * @throws ApiException
     */
    public function getKeysByOrderId(RetrieveByOrderIdDTO $retrieveByOrderIdDTO): string
    {
        if ($retrieveByOrderIdDTO->user_id == null) {
            $technician_id = $this->technicianOrderService->getTecnicianIdByOrderId($retrieveByOrderIdDTO->order_id);
            if (!isset($technician_id)) {
                return throw new ApiException('No technician found for this order_id: ' . $retrieveByOrderIdDTO->order_id);
            }
            $retrieveByOrderIdDTO->user_id = $technician_id;
        }

        $orderItems = $this->orderItemService->getQtyKeyTypeByOrderId($retrieveByOrderIdDTO->order_id)->toArray();

        if (empty($orderItems)) {
            return throw new ApiException('No windows keys found for this order_id: ' . $retrieveByOrderIdDTO->order_id);
        }

        foreach ($orderItems as $orderItem) {
            if (str_contains($orderItem['productid'], strtoupper(WindowsKey::TYPE_HOME))) {
                $quantityByType[WindowsKey::TYPE_HOME] = $orderItem['qty'];
            }
            if (str_contains($orderItem['productid'], strtoupper(WindowsKey::TYPE_PRO))) {
                $quantityByType[WindowsKey::TYPE_PRO] = $orderItem['qty'];
            }
        }

        $keys = collect();

        $downloadedKeysCount = WindowsKey::query()
            ->where('order_id', $retrieveByOrderIdDTO->order_id)
            ->count();
        if (array_sum($quantityByType) <= $downloadedKeysCount) {
            return throw new ApiException('You already get keys, you need to RMA keys before getting new ones');
        }

        foreach ($quantityByType as $type => $quantity) {
            $keys->put($type, WindowsKey::query()
                ->where('key_type', $type)
                ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
                ->whereNull('order_id')
                ->limit($quantity)
                ->get());
        }

        if ($keys->flatten()->isEmpty()) {
            return throw new ApiException('Keys not found');
        }

        $keyIds = $keys->flatten()->pluck('id');
        $resultingKeys = $keys->flatten()->map(function ($key) {
            $key->key = decrypt($key->key);
            return $key;
        });

        if (!empty($keyIds)) {
            WindowsKey::query()
                ->whereIn('id', $keyIds)
                ->update(['status' => WindowsKey::KEY_TYPE_DOWNLOADED, 'order_id' => $retrieveByOrderIdDTO->order_id]);
        }

        collect($quantityByType)->each(function ($quantity, $type) use ($retrieveByOrderIdDTO) {
            OrderWindowsKeys::create([
                'order_id' => $retrieveByOrderIdDTO->order_id,
                'key_type' => $type,
                'quantity' => $quantity
            ]);
        });

        $retrieveByOrderIdDTO->ids = $keyIds->toArray();

        return $this->generateExportPath($resultingKeys->toArray());
    }

    public function getKeysByQuantity(RetrieveByQuantityDTO $retrieveByQuantityDTO): string
    {
        $windowsKeysHome = [];
        $windowsKeysPro = [];
        if (isset($retrieveByQuantityDTO->quantities['pro'])) {
            $windowsKeysPro = WindowsKey::query()
                ->select('id', 'key', 'key_type', 'vendor', 'status')
                ->where('key_type', WindowsKey::TYPE_PRO)
                ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
                ->whereNull('order_id')
                ->limit($retrieveByQuantityDTO->quantities['pro'])
                ->get()->toArray();
        }
        if (isset($retrieveByQuantityDTO->quantities['home'])) {
            $windowsKeysHome = WindowsKey::query()
                ->select('id', 'key', 'key_type', 'vendor', 'status')
                ->where('key_type', WindowsKey::TYPE_HOME)
                ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
                ->whereNull('order_id')
                ->limit($retrieveByQuantityDTO->quantities['home'])
                ->get()->toArray();
        }
        if (count($windowsKeysHome) != $retrieveByQuantityDTO->quantities['home'] || count($windowsKeysPro) != $retrieveByQuantityDTO->quantities['pro']) {
            return throw new ApiException('There are not enough keys in the database.');
        }
        $resultKeys = array_merge($windowsKeysPro, $windowsKeysHome);
        $keyIds = [];
        foreach ($resultKeys as &$key) {
            $key['key'] = decrypt($key['key']);
            $keyIds[] = $key['id'];
        }
        if (!empty($keyIds)) {
            WindowsKey::query()
                ->whereIn('id', $keyIds)
                ->update(['status' => WindowsKey::KEY_TYPE_TRANSFERRED]);
        }
        $retrieveByQuantityDTO->ids = $keyIds;
        return $this->generateExportPath($resultKeys);
    }

    public function generateExportPath($keys): string
    {
        $fileName = 'windows_keys_' . time() . '.xlsx';
        Excel::store(new WindowsKeysExport($keys), $fileName, 'public');

        return Storage::url($fileName);
    }

    public function getKeyTypeBySerialNumber($retrievedDTO): string
    {
        $technicianOrder = $this->technicianOrderService->getOrderBySerialNumber($retrievedDTO->serial_key);

        if (empty($technicianOrder)) {
            return throw new ApiException('No technician found with this serial_key: ' . $retrievedDTO->serial_key);
        }

        $retrievedDTO->user_id = $technicianOrder->technician_id;
        $retrievedDTO->order_id = $technicianOrder->order_id;

        $keyType = $this->componentService->getOSByOrderItemId($technicianOrder->order_item_id, $technicianOrder->order_id);

        if (!$keyType) {
            $productDetails = [];
            $sku = $this->orderItemService->getOrderSku($technicianOrder->order_id);
            if ($this->technicianOrderService->isCDTSku($sku)) {
                $productDetails = $this->componentService->getCDTDetailsFromComponents($technicianOrder->order_id);
            } else {
                $productDetails = $this->componentService->getProductDetails($sku);
            }
            $keyType = $productDetails['os'];
        }
        $resultKeyType = '';
        $keyType = strtoupper($keyType);
        if ($keyType && str_contains($keyType, strtoupper(WindowsKey::TYPE_PRO))) {
            $resultKeyType = WindowsKey::TYPE_PRO;
        }
        if ($keyType && str_contains($keyType, strtoupper(WindowsKey::TYPE_HOME))) {
            $resultKeyType = WindowsKey::TYPE_HOME;
        }

        return $resultKeyType;
    }


    public function setAsRmaNeeded(UpdateDTO $updateDTO): array
    {
        $refundKeys = $this->getRefundKeys($updateDTO->ids);
        $homeKeys = $refundKeys->where('key_type', WindowsKey::TYPE_HOME)->toArray();
        $proKeys = $refundKeys->where('key_type', WindowsKey::TYPE_PRO)->toArray();
        $order_id = $refundKeys->first()->order_id ?? null;

        $refundKeys->each->update(['status' => WindowsKey::KEY_TYPE_REFUND]);

        $downloadLink = null;
        if ($updateDTO->need_to_download_new_keys) {
            $windowsKeysHome = $this->getNewKeysByType(WindowsKey::TYPE_HOME, $homeKeys, $order_id);
            $windowsKeysPro = $this->getNewKeysByType(WindowsKey::TYPE_PRO, $proKeys, $order_id);

            $windowsKeys = $windowsKeysPro->union($windowsKeysHome);
            if ($windowsKeys->isEmpty()) {
                throw new ApiException('There are no keys available to get');
            }

            $newKeys = $this->decryptKeys($windowsKeys);
            $updateDTO->ids = array_column($newKeys, 'id');
            $downloadLink = $this->generateExportPath($newKeys);
        }

        $updateDTO->keys_to_send = $refundKeys->pluck('id')->toArray();

        event(new KeysRetrievedEvent($updateDTO));
        event(new RefundKeysEvent($updateDTO));

        return [
            'data' => [
                'downloadLink' => $downloadLink
            ],
            'status' => 'success',
            'message' => 'Keys refund successfully!' . ($downloadLink ? ' Downloaded new keys' : ''),
        ];
    }

    private function getRefundKeys(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return WindowsKey::query()
            ->select('id', 'status', 'key_type', 'order_id')
            ->whereIn('id', $ids)
            ->where('status', WindowsKey::KEY_TYPE_DOWNLOADED)
            ->get();
    }

    private function getNewKeysByType(string $type, array $keys, ?string $order_id): \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
    {
        if (!empty($keys)) {
            $windowsKeys = WindowsKey::query()
                ->whereNull('serial_key')
                ->whereNull('order_id')
                ->where('status', WindowsKey::KEY_TYPE_NOT_USED)
                ->where('key_type', $type)
                ->limit(count($keys))
                ->get();

            if ($windowsKeys->isEmpty()) {
                throw new ApiException("There are no {$type} keys available to get");
            }

            $windowsKeys->each->update(['status' => WindowsKey::KEY_TYPE_DOWNLOADED, 'order_id' => $order_id]);

            return $windowsKeys;
        }

        return collect();
    }

    public function markAsUnused(): array
    {
        WindowsKey::query()->update(['status' => WindowsKey::KEY_TYPE_NOT_USED, 'order_id' => null, 'serial_key' => null]);
        return [
            'status' => 'success',
            'message' => 'Keys marked as unused',
        ];
    }
    public function handleRMAError(HandleRMAErrorDTO $handleRMAErrorDTO): array
    {
        $windowsKey = WindowsKey::query()->where('serial_key', $handleRMAErrorDTO->serial_key)->where('status', WindowsKey::KEY_TYPE_USED);

        if (!$windowsKey->exists()) {
            return throw new ApiException('There are no used keys with this serial number');
        }

        $technicianOrder = $this->technicianOrderService->getOrderBySerialNumber($handleRMAErrorDTO->serial_key);
        $handleRMAErrorDTO->user_id = $technicianOrder->technician_id;
        $handleRMAErrorDTO->order_id = $technicianOrder->order_id;

        if ($windowsKey->exists()) {
            $handleRMAErrorDTO->entity_id = $windowsKey->pluck('id')->last();
            event(new RefundKeyEvent($handleRMAErrorDTO));
        }
        $windowsKey->update([
            'status' => WindowsKey::KEY_TYPE_RMA_NEEDED,
            'rma_error' => $handleRMAErrorDTO->rma_error,
        ]);
        return [
            'status' => 'success',
            'message' => 'Key marked as RMA Needed, error saved successfully',
        ];
    }


    public function updateStatus(UpdateStatusDTO $updateStatusDTO): array
    {
        WindowsKey::query()
            ->whereIn('id', $updateStatusDTO->ids)
            ->update([
                'status' => $updateStatusDTO->status,
            ]);

        if ($updateStatusDTO->status === WindowsKey::KEY_TYPE_NOT_USED) {
            WindowsKey::query()
                ->whereIn('id', $updateStatusDTO->ids)
                ->update([
                    'serial_key' => null,
                    'order_id' => null,
                ]);
        }

        $updateStatusDTO->logKeys = $updateStatusDTO->ids;

        event(new UpdateStatusEvent($updateStatusDTO));

        return [
            'status' => 'success',
            'message' => 'Status update successfully',
        ];
    }

}
