<?php

namespace App\Services\QualityCheck;

use App\Models\SbHistoryOrderItemComponent;
use App\Models\SbOrderItemComponent;
use App\Models\SbQcData;
use App\Services\Kits\KitService;
use App\Services\Parts\PartService;

class QcDataService
{
    public function getRaidByOrderId(string $orderId): ?string
    {
        $raidValue = SbQcData::query()->where('orderid', $orderId)->where('Raid', '!=', '')->select('Raid')->first()?->Raid;

        return match($raidValue) {
            'Raid 1' => 1,
            'Raid 0' => 0,
            default => null
        };

    }

}
