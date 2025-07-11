<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\RetrievedDTO;

class RefundKeyEvent
{

    public function __construct($retrievedDTO)
    {
        $this->retrievedDTO = $retrievedDTO;
    }
}
