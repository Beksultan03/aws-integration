<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\ImportedEvent;
use App\Listeners\Logs\WindowsLogListener;

class LogImportedEventListeners extends WindowsLogListener
{
    public function handle(ImportedEvent $event): void
    {

        $user = $this->getUser($event->user_id);
        $this->logEvent(
            'ImportedEvent',
            'Key uploaded by %s',
            $user,
            $event->newKeys,
        );
    }
}
