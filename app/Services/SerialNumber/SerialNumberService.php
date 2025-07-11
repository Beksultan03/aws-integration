<?php

namespace App\Services\SerialNumber;

use App\Models\SbSerialNumber;
use App\Models\SerialNumberLocationChangeHistory;
use Illuminate\Support\Facades\DB;

class SerialNumberService
{
    public function getOrderId(string $serialNumber): ?SerialNumberLocationChangeHistory
    {
        $serialHistory = SbSerialNumber::query()
            ->where('serial_number', $serialNumber)
            ->whereHas('locationChangeHistory', function ($query) {
                $query->where('shipped_location_order_id', '!=', 0)
                    ->whereNotNull('shipped_location_order_id');
            })
            ->with(['locationChangeHistory' => function ($query) {
                $query->select('shipped_location_order_id as order_id');
            }])
            ->select('serial_id')
            ->first();

        return $serialHistory?->locationChangeHistory->first() ?? null;
    }

    public function getSerialNumberValue(string $serialNumber)
    {
        return DB::table("tbl_sb_serial_numbers")
            ->where('serial_number', $serialNumber)
            ->select('serial_id', 'product_id')
            ->first();
    }

    public function getWithSku(array $serialNumber): array
    {
        return SbSerialNumber::query()
            ->select('b.sku', 's.serial_number')
            ->from('tbl_sb_serial_numbers as s')
            ->leftJoin('tbl_base_product as b', 'b.id', '=', 's.product_id')
            ->whereIn('s.serial_number', $serialNumber)
            ->pluck('s.serial_number', 'b.sku')->toArray();
    }

}
