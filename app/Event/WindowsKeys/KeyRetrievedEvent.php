<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\RetrievedDTO;

class KeyRetrievedEvent
{
    public function __construct(RetrievedDTO $retrievedDTO)
    {
        $this->retrievedDTO = $retrievedDTO;
    }
}
