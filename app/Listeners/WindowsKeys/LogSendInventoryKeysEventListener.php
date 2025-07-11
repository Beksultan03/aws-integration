<?php

namespace App\Listeners\WindowsKeys;
use App\Event\WindowsKeys\LogSentInventoryKeysEvent;
use App\Listeners\Logs\WindowsLogListener;
use App\Logger\LoggerInterface;
use App\Models\Log;
use App\Models\WindowsKey;
use JetBrains\PhpStorm\NoReturn;


class LogSendInventoryKeysEventListener
{
    public function __construct(protected LoggerInterface $logger) {}
    public function handle(LogSentInventoryKeysEvent $event): void
    {
        $this->logger->log("Inventory alert sent to email", WindowsKey::class);
    }
}
