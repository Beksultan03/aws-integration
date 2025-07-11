<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;

class RetrieveByOrderIdDTO extends BaseDTO
{

    public function __construct(public int $order_id, public string $message, public ?string $user_id = null, public ?array $ids = null) {}
}
