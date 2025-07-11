<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\RetrieveByOrderIdDTO;

class KeysRetrievedEvent
{
    public function __construct($retrievedDTO)
    {
        $this->retrievedDTO = $retrievedDTO;
    }
}
