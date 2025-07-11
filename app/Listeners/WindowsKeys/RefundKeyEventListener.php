<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\RefundKeyEvent;
use App\Listeners\Logs\WindowsLogListener;
use App\Models\SbUser;

class RefundKeyEventListener extends WindowsLogListener
{
    public function handle(RefundKeyEvent $event): void
    {
        $messageTemplate = sprintf("Key refunded by %%s with error '%s'", $event->retrievedDTO->rma_error);
        $user = $this->getUser($event->retrievedDTO->user_id);
        $this->logSingleEvent('RefundKeyEvent', $messageTemplate, $event->retrievedDTO->entity_id, $user);
    }
}
