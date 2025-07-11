<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;

class SendToManufacturerDTO extends BaseDTO
{
    public function __construct(public array $ids, public int $user_id, public ?array $keys_to_send = null) {}
}
