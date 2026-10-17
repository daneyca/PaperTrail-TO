<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ProcurementWorkflowNotification;
use App\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWorkflowEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $recipientId,
        public ProcurementWorkflowNotification $notification,
        public ?Model $related = null,
    ) {
    }

    public function handle(): void
    {
        $recipient = User::find($this->recipientId);

        if (! $recipient || ! filled($recipient->email) || ! config('papertrail.email_notifications_enabled', true)) {
            return;
        }

        $recipient->notify($this->notification);

        $this->audit('Email Notification Sent', 'Workflow email notification sent.', $recipient);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('PaperTrail workflow email failed.', [
            'subject' => $this->notification->subject,
            'recipient_id' => $this->recipientId,
            'exception' => $exception?->getMessage(),
        ]);

        $this->audit('Email Notification Failed', 'Workflow email notification failed.', User::find($this->recipientId), 'warning');
    }

    private function audit(string $action, string $description, ?User $recipient, string $severity = 'info'): void
    {
        try {
            AuditLogger::log('Email Notifications', $action, $description, $this->related, null, null, $severity, [
                'subject' => $this->notification->subject,
                'recipient_user_id' => $recipient?->user_id,
                'recipient_email_present' => filled($recipient?->email),
            ]);
        } catch (Throwable $exception) {
            Log::warning('PaperTrail email audit logging failed.', [
                'action' => $action,
                'recipient_id' => $this->recipientId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
