<?php

namespace App\Listeners\WindowsKeys;

use App\Event\WindowsKeys\LogSentRMAKeysEmailEvent;
use App\Event\WindowsKeys\SendInventoryKeysEvent;
use App\Listeners\Logs\WindowsLogListener;
use App\Models\Log;
use App\Models\WindowsKey;
use App\Services\Emails\EmailService;

class SendRMAKeysEmailEventListener extends WindowsLogListener
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function handle(SendInventoryKeysEvent $event): void
    {
        $windowsKeysCount = WindowsKey::query()
            ->where('status', WindowsKey::KEY_TYPE_RMA_NEEDED)
            ->count();

        if ($windowsKeysCount && $this->needToSendEmail()) {
            $message = view('emails.need_to_rma_alert', compact('windowsKeysCount'))->render();
            $subject = 'RMA Keys Alert';

            $this->emailService->sendEmail($subject, $message);

            event(new LogSentRMAKeysEmailEvent());
        }
    }

    private function needToSendEmail(): bool
    {
        $neededWeeksBeforeSending = 2;
        $twoWeeksAgo = now()->subWeeks($neededWeeksBeforeSending);

        $recentAlertExists = Log::query()
            ->where('type', WindowsKey::class)
            ->where('text', 'RMA keys alert sent to email')
            ->where('created_at', '>=', $twoWeeksAgo)
            ->exists();

        return !$recentAlertExists;
    }
}
