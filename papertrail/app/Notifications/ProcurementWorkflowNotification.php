<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProcurementWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $message,
        public ?string $actionUrl = null,
        public string $actionText = 'View Document',
        public ?string $documentType = null,
        public ?string $trackingNumber = null,
        public ?string $status = null,
        public array $metadata = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        if (! config('papertrail.email_notifications_enabled', true) || ! filled($notifiable->email ?? null)) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hello ' . ($notifiable->name ?: 'PaperTrail User') . ',')
            ->line($this->message);

        if ($this->documentType) {
            $mail->line('Document Type: ' . $this->documentType);
        }

        if ($this->trackingNumber) {
            $mail->line('Tracking Number: ' . $this->trackingNumber);
        }

        if ($this->status) {
            $mail->line('Status: ' . str($this->status)->replace('_', ' ')->title());
        }

        if ($this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail->line('This is an automated notification from PaperTrail.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->subject,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'action_text' => $this->actionText,
            'document_type' => $this->documentType,
            'tracking_number' => $this->trackingNumber,
            'status' => $this->status,
            'metadata' => $this->metadata,
        ];
    }
}
