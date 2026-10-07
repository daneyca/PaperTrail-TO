<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $code,
        private int $expiresInMinutes = 10,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('PaperTrail Email Verification Code')
            ->greeting('Hello ' . ($notifiable->name ?: 'PaperTrail user') . ',')
            ->line('Use the verification code below to verify your official PaperTrail email address.')
            ->line('Verification Code: ' . $this->code)
            ->line("This code will expire in {$this->expiresInMinutes} minutes.")
            ->line('If you did not request this code, please ignore this email.')
            ->salutation('PaperTrail - LGU Tomas Oppus');
    }
}
