<?php

namespace App\Http\DTO\WindowsKeys;

use App\Http\DTO\BaseDTO;
use Illuminate\Http\UploadedFile;

class UploadDTO extends BaseDTO
{
    public function __construct(public UploadedFile $file, public int $user_id) {}
}
