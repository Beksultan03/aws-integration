<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;

class RetrievedDTO extends BaseDTO
{

    public function __construct(public ?string $serial_key = null, public ?int $order_id = null, public ?string $user_id = null, public ?string $entity_id = null, public ?string $key_type = null) {}
}
