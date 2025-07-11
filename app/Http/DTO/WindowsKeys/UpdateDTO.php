<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;

class UpdateDTO extends BaseDTO
{
    public function __construct(public array $ids, public int $user_id, public bool $need_to_download_new_keys, public ?array $keys_to_send = null) {}
}
