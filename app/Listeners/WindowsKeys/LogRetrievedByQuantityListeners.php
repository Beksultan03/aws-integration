<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\KeysRetrievedEvent;
use App\Listeners\Logs\WindowsLogListener;

class LogRetrievedByQuantityListeners extends WindowsLogListener
{
    public function handle(KeysRetrievedEvent $event): void
    {
        $user = $this->getUser($event->retrievedDTO->user_id);
        $this->logEvent(
            'KeysRetrievedEvent',
            'Key '. $event->retrievedDTO->message .' by %s',
            $user,
            $event->retrievedDTO->ids,
        );
    }
}
