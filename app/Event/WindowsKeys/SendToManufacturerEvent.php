<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\BaseDTO;
use App\Http\DTO\WindowsKeys\SendToManufacturerDTO;

class SendToManufacturerEvent
{

    public function __construct(SendToManufacturerDTO $sendToManufacturer)
    {
        $this->sendToManufacturerDTO = $sendToManufacturer;
    }
}
