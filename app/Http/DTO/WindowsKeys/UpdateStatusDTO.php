<?php

namespace App\Http\DTO\WindowsKeys;

class UpdateStatusDTO
{
    public function __construct(public array $ids, public int $user_id, public string $status, public ?array $logKeys = []) {}
}
