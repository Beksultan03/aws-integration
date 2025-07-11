<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\UpdateStatusEvent;
use App\Listeners\Logs\WindowsLogListener;

class LogUpdateStatusEventListener extends WindowsLogListener
{
    public function handle(UpdateStatusEvent $event): void
    {
        $user = $this->getUser($event->updateStatusDTO->user_id);
        $this->logEvent(
            'SendToManufacturerEvent',
            'Status updated by %s to status: ' . $event->updateStatusDTO->status,
            $user,
            $event->updateStatusDTO->logKeys,
        );
    }
}
