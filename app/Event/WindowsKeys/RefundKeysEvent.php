<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\UpdateDTO;

class RefundKeysEvent
{

    public function __construct(UpdateDTO $updateDTO)
    {
        $this->updateDTO = $updateDTO;
    }
}
