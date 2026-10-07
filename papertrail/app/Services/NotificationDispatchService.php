<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use App\Notifications\ProcurementWorkflowNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationDispatchService
{
    public function notifyUser(
        int|User|null $user,
        string $subject,
        string $message,
        ?string $url = null,
        array $metadata = [],
        string $type = SystemNotification::TYPE_INFO,
        ?string $module = 'System',
        ?Model $related = null,
        string $actionText = 'View Document',
    ): ?SystemNotification {
        $target = $user instanceof User ? $user : User::find($user);

        if (! $target) {
            return null;
        }

        $templateKey = $metadata['template_key'] ?? $metadata['event'] ?? null;
        $template = $templateKey
            ? app(RuleConfigurationService::class)->renderNotificationTemplate((string) $templateKey, $this->templateData($target, $message, $url, $metadata, $related))
            : [];

        $subject = $template['subject'] ?? $subject;
        $message = $template['body'] ?? $message;
        $actionText = $template['action_text'] ?? $actionText;

        if (! empty($template['channel'])) {
            $metadata['channel'] = $template['channel'];
        }

        $this->logWorkflowNotificationTriggered($target, $subject, $metadata, $related);

        $notification = SystemNotification::create([
            'user_id' => $target->id,
            'title' => $subject,
            'message' => $message,
            'type' => $type,
            'module' => $module,
            'related_type' => $related ? $related::class : null,
            'related_id' => $related?->getKey(),
            'action_url' => $url,
        ]);

        $this->sendEmail($target, $subject, $message, $url, $metadata, $related, $actionText);

        return $notification;
    }

    public function notifyRole(
        string $roleCode,
        string $subject,
        string $message,
        ?string $url = null,
        array $metadata = [],
    ): int {
        return $this->activeUsersByRole($roleCode)
            ->get()
            ->map(fn (User $user) => $this->notifyUser($user, $subject, $message, $url, $metadata))
            ->filter()
            ->count();
    }

    public function notifyOffice(
        int|string|null $officeId,
        string $subject,
        string $message,
        ?string $url = null,
        array $metadata = [],
    ): int {
        if (! $officeId) {
            return 0;
        }

        return User::query()
            ->where('office_id', $officeId)
            ->where('status', User::STATUS_ACTIVE)
            ->get()
            ->map(fn (User $user) => $this->notifyUser($user, $subject, $message, $url, $metadata))
            ->filter()
            ->count();
    }

    public function notifyDocumentOwner(
        ?Model $document,
        string $subject,
        string $message,
        ?string $url = null,
        array $metadata = [],
    ): ?SystemNotification {
        if (! $document) {
            return null;
        }

        $document->loadMissing(['submittedBy', 'preparedBy']);

        $owner = $document->submittedBy
            ?? $document->preparedBy
            ?? (isset($document->submitted_by_user_id) ? User::find($document->submitted_by_user_id) : null)
            ?? (isset($document->prepared_by_user_id) ? User::find($document->prepared_by_user_id) : null);

        return $this->notifyUser($owner, $subject, $message, $url, $metadata, related: $document);
    }

    private function sendEmail(
        User $user,
        string $subject,
        string $message,
        ?string $url,
        array $metadata,
        ?Model $related,
        string $actionText,
    ): void {
        if (! config('papertrail.email_notifications_enabled', true)) {
            $this->auditEmail('Email Notification Skipped', 'Email notifications are disabled.', $user, $related, [
                'reason' => 'disabled',
                'subject' => $subject,
            ]);

            return;
        }

        if (($metadata['channel'] ?? null) === 'in_app') {
            $this->auditEmail('Email Notification Skipped', 'Template channel is in-app only.', $user, $related, [
                'reason' => 'in_app_only',
                'subject' => $subject,
            ]);

            return;
        }

        if (! filled($user->email)) {
            $this->auditEmail('Email Notification Skipped', 'User has no email address.', $user, $related, [
                'reason' => 'missing_email',
                'subject' => $subject,
                'user_id' => $user->user_id,
            ]);

            return;
        }

        try {
            $user->notify(new ProcurementWorkflowNotification(
                subject: $subject,
                message: $message,
                actionUrl: $url,
                actionText: $metadata['action_text'] ?? $actionText,
                documentType: $metadata['document_type'] ?? $this->documentType($related),
                trackingNumber: $metadata['tracking_number'] ?? $this->trackingNumber($related),
                status: $metadata['status'] ?? $this->status($related),
                metadata: $metadata,
            ));

            $this->auditEmail('Email Notification Sent', 'Workflow email notification sent.', $user, $related, [
                'subject' => $subject,
                'recipient_user_id' => $user->user_id,
                'recipient_email_present' => filled($user->email),
            ]);
        } catch (Throwable $exception) {
            Log::error('PaperTrail workflow email failed.', [
                'subject' => $subject,
                'user_id' => $user->id,
                'user_identifier' => $user->user_id,
                'related_type' => $related ? $related::class : null,
                'related_id' => $related?->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            $this->auditEmail('Email Notification Failed', 'Workflow email notification failed.', $user, $related, [
                'subject' => $subject,
                'recipient_user_id' => $user->user_id,
                'error' => $exception->getMessage(),
            ], 'warning');
        }
    }

    private function activeUsersByRole(string $roleCode)
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(function ($query) use ($roleCode) {
                $query->where('role', $roleCode)
                    ->orWhere('user_id', $roleCode)
                    ->orWhereHas('assignedRole', function ($role) use ($roleCode) {
                        $role->where('code', $roleCode)
                            ->orWhere('name', $roleCode);
                    });
            });
    }

    private function documentType(?Model $related): ?string
    {
        if (! $related) {
            return null;
        }

        return $related->document_type
            ?? class_basename($related);
    }

    private function trackingNumber(?Model $related): ?string
    {
        if (! $related) {
            return null;
        }

        if (method_exists($related, 'displayNumber')) {
            return $related->displayNumber();
        }

        return $related->tracking_number
            ?? $related->pr_no
            ?? $related->resolution_number
            ?? null;
    }

    private function status(?Model $related): ?string
    {
        return $related?->status;
    }

    private function templateData(User $user, string $message, ?string $url, array $metadata, ?Model $related): array
    {
        return array_merge([
            'document_type' => $metadata['document_type'] ?? $this->documentType($related),
            'tracking_number' => $metadata['tracking_number'] ?? $this->trackingNumber($related),
            'office_name' => $metadata['office_name'] ?? ($related?->office_name ?? $related?->department_name ?? $related?->submittingOffice?->name ?? ''),
            'sender_name' => $metadata['sender_name'] ?? auth()->user()?->name ?? 'PaperTrail',
            'receiver_name' => $metadata['receiver_name'] ?? $user->name,
            'status' => $metadata['status'] ?? $this->status($related),
            'action_url' => $url,
            'date' => now()->format('M d, Y'),
            'message' => $message,
        ], $metadata);
    }

    private function logWorkflowNotificationTriggered(User $user, string $subject, array $metadata, ?Model $related): void
    {
        Log::info('Workflow email notification triggered', [
            'event' => $metadata['event'] ?? str($subject)->slug('_')->toString(),
            'recipient_user_id' => $user->id,
            'recipient_email_present' => filled($user->email),
            'document_type' => $metadata['document_type'] ?? $this->documentType($related),
            'document_id' => $related?->getKey() ?? $metadata['document_id'] ?? null,
        ]);
    }

    private function auditEmail(string $action, string $description, User $user, ?Model $related, array $metadata = [], string $severity = 'info'): void
    {
        try {
            AuditLogger::log('Email Notifications', $action, $description, $related, null, null, $severity, $metadata);
        } catch (Throwable $exception) {
            Log::warning('PaperTrail email audit logging failed.', [
                'action' => $action,
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
