<?php

namespace App\Services\Emails;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    public function sendEmail(string $subject, string $message): void
    {
        $recipients = explode(',', env('INVENTORY_ALERT_EMAILS'));

        $toEmail = array_shift($recipients);
        $ccEmails = $recipients;

        Mail::send([], [], function (Message $mail) use ($subject, $message, $toEmail, $ccEmails) {
            $mail->from(env('MAIL_FROM_ADDRESS'))
                ->to($toEmail)
                ->cc($ccEmails)
                ->subject($subject)
                ->html($message);
        });
    }
}
