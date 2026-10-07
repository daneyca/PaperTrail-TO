<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SigningCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $documentLabel,
        public string $actionLabel = 'Confirm / Sign',
    ) {
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email ?? null) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('PaperTrail Signing Code')
            ->greeting('Hello ' . ($notifiable->name ?: 'PaperTrail User') . ',')
            ->line('Your PaperTrail signing code is:')
            ->line($this->code)
            ->line('This code will expire in 10 minutes.')
            ->line('Document: ' . $this->documentLabel)
            ->line('Action: ' . $this->actionLabel)
            ->line('If you did not request this signing code, please contact the administrator.')
            ->salutation('PaperTrail - LGU Tomas Oppus');
    }
}
