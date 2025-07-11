<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\KeyRetrievedEvent;
use App\Listeners\Logs\WindowsLogListener;
use App\Models\WindowsKey;

class LogRetriveEventListener extends WindowsLogListener
{
    public function handle(KeyRetrievedEvent $event): void
    {
        $user = $this->getUser($event->retrievedDTO->user_id);
        $text = "Key for serial number " . $event->retrievedDTO->serial_key . " used by " . $user->full_name;

        $this->logger->log($text, WindowsKey::class, $event->retrievedDTO->entity_id);
    }
}
