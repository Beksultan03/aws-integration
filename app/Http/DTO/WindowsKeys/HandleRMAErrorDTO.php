<?php

namespace App\Http\DTO\WindowsKeys;

class HandleRMAErrorDTO
{
    public function __construct(public string $serial_key, public string $rma_error, public ?int $order_id = null, public ?string $user_id = null, public ?string $entity_id = null) {}
}
