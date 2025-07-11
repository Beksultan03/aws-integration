<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\SendToManufacturerEvent;
use App\Listeners\Logs\WindowsLogListener;

class LogSendToManufacturerListeners extends WindowsLogListener
{
    public function handle(SendToManufacturerEvent $event): void
    {
        $user = $this->getUser($event->sendToManufacturerDTO->user_id);
        $this->logEvent(
            'SendToManufacturerEvent',
            'Key sent to manufacturer by %s',
            $user,
            $event->sendToManufacturerDTO->keys_to_send,
        );

    }
}
