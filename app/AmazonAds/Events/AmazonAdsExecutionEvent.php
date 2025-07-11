<?php

namespace App\AmazonAds\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

class AmazonAdsExecutionEvent extends Event
{
    use Dispatchable, SerializesModels;

    public $action;
    public $data;

    public function __construct(string $action, array $data)
    {
        $this->action = $action;
        $this->data = $data;
    }
}
