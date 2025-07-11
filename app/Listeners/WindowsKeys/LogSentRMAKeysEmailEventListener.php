<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\LogSentRMAKeysEmailEvent;
use App\Logger\LoggerInterface;
use App\Models\WindowsKey;

class LogSentRMAKeysEmailEventListener
{
    public function __construct(protected LoggerInterface $logger) {}
    public function handle(LogSentRMAKeysEmailEvent $event): void
    {
        $this->logger->log("RMA keys alert sent to email", WindowsKey::class);
    }
}
