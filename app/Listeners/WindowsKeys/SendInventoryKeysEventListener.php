<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\LogSentInventoryKeysEvent;
use App\Event\WindowsKeys\SendInventoryKeysEvent;
use App\Models\Log;
use App\Models\WindowsKey;
use App\Services\Emails\EmailService;
use App\Services\WindowsKeys\WindowsKeyService;


class SendInventoryKeysEventListener
{
    private WindowsKeyService $windowsKeyService;
    private EmailService $emailService;

    public function __construct(WindowsKeyService $windowsKeyService, EmailService $emailService)
    {
        $this->windowsKeyService = $windowsKeyService;
        $this->emailService = $emailService;
    }
    public function handle(SendInventoryKeysEvent $event): void
    {
        $analyticsData = $this->windowsKeyService->getAnalyticsData();

        if ($this->needToSendEmail($analyticsData)) {
            $headerColor = 'e63946';
            $subject = 'Inventory Alert: Key Usage and Stock Update';
            $sections = [
                'Pro' => $headerColor,
                'Home' => $headerColor,
                'Total' => $headerColor,
            ];
            $message = view('emails.inventory_alert', compact('sections', 'analyticsData'))->render();

            $this->emailService->sendEmail($subject, $message);

            event(new LogSentInventoryKeysEvent());

        }
    }

    private function needToSendEmail($analyticsData): bool
    {
        $recentAlertExists = Log::query()
            ->where('type', WindowsKey::class)
            ->where('text', 'Inventory alert sent to email')
            ->where('created_at', '>=', now()->subDay())
            ->exists();
        $isUsedMoreThanStock = $analyticsData['Home']['used_last_14_days'] >= $analyticsData['Home']['keys_in_stock']
            || $analyticsData['Pro']['used_last_14_days'] >= $analyticsData['Pro']['keys_in_stock'];

        return $isUsedMoreThanStock && !$recentAlertExists;
    }
}
