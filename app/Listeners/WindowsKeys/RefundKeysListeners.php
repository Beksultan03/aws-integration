<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\RefundKeysEvent;
use App\Listeners\Logs\WindowsLogListener;

class RefundKeysListeners extends WindowsLogListener
{
    public function handle(RefundKeysEvent $event): void
    {
        $user = $this->getUser($event->updateDTO->user_id);
        $this->logEvent(
            'RefundKeysEvent',
            'Key refund by %s',
            $user,
            $event->updateDTO->keys_to_send,
        );
    }
}
