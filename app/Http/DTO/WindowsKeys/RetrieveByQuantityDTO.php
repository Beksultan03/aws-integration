<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;

class RetrieveByQuantityDTO extends BaseDTO
{
    public function __construct(public string $user_id, public string $message, public array $quantities, public ?array $ids = null) {}
}
