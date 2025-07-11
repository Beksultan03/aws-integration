<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\UpdateStatusDTO;

class UpdateStatusEvent
{
    public function __construct(UpdateStatusDTO $updateStatusDTO)
    {
        $this->updateStatusDTO = $updateStatusDTO;
    }
}
