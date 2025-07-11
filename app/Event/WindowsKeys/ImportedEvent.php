<?php

namespace App\Event\WindowsKeys;

use App\Http\DTO\WindowsKeys\UploadDTO;
use Illuminate\Support\Facades\Event;

class ImportedEvent extends Event
{

  public function __construct(int $user_id, array $new_keys)
  {
      $this->user_id = $user_id;
      $this->newKeys = $new_keys;
  }
}
