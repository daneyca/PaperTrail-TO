<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProcurementDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        '_token',
        'api_key',
        'openai_api_key',
        'smtp_key',
        'mail_password',
        'mail_username',
        'verification_code',
        'email_verification_code',
        'email_verification_code_hash',
        'signing_code',
        'signing_code_hash',
        'drawn_signature_data',
        'secret',
        'remember_token',
        'session',
        'session_id',
        'csrf',
    ];

    private const LARGE_CONTENT_KEYS = [
        'document_html',
        'html',
        'raw_html',
        'content_html',
        'rendered_document',
        'print_html',
    ];

    public static function log(
        string $first,
        string|array|null $second = null,
        ?string $description = null,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $severity = 'info',
        array $metadata = [],
    ): void {
        if (is_array($second) || $second === null) {
            self::write($first, $second ?? []);

            return;
        }

        self::write($second, [
            'module' => $first,
            'description' => $description,
            'auditable' => $auditable,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => $severity,
            'metadata' => $metadata,
        ]);
    }

    public static function document(string $action, ?Model $document, array $data = []): void
    {
        self::write($action, array_merge([
            'module' => $data['module'] ?? 'Documents',
            'auditable' => $document,
            'document' => $document,
        ], $data));
    }

    public static function workflow(string $action, ?Model $document, array $data = []): void
    {
        self::write($action, array_merge([
            'module' => $data['module'] ?? 'Workflow',
            'auditable' => $document,
            'document' => $document,
        ], $data));
    }

    public static function security(string $action, array $data = []): void
    {
        self::write($action, array_merge([
            'module' => $data['module'] ?? 'Security',
            'severity' => $data['severity'] ?? 'warning',
        ], $data));
    }

    public static function ai(string $action, ?Model $document = null, array $data = []): void
    {
        self::write($action, array_merge([
            'module' => $data['module'] ?? 'AI',
            'auditable' => $document,
            'document' => $document,
        ], $data));
    }

    public static function signature(string $action, ?Model $document = null, array $data = []): void
    {
        self::write($action, array_merge([
            'module' => $data['module'] ?? 'E-Signature',
            'auditable' => $document,
            'document' => $document,
            'severity' => $data['severity'] ?? 'notice',
        ], $data));
    }

    public static function failed(string $action, array $data = []): void
    {
        self::write($action, array_merge([
            'status' => 'failed',
            'severity' => $data['severity'] ?? 'warning',
        ], $data));
    }

    public static function denied(string $action, array $data = []): void
    {
        self::write($action, array_merge([
            'status' => 'denied',
            'severity' => $data['severity'] ?? 'warning',
            'module' => $data['module'] ?? 'Access Control',
        ], $data));
    }

    private static function write(string $action, array $data = []): void
    {
        try {
            $user = Auth::user();
            $auditable = self::modelFrom($data['auditable'] ?? $data['target'] ?? $data['document'] ?? null);
            $document = self::modelFrom($data['document'] ?? null) ?? self::documentFrom($auditable);
            $role = $user?->assignedRole;
            $office = $user?->assignedOffice;
            $severity = $data['severity'] ?? 'info';

            AuditLog::create([
                'event_uuid' => (string) Str::uuid(),
                'user_id' => $user?->id,
                'user_identifier' => $user?->user_id,
                'user_name' => $user?->name,
                'role_id' => $user?->role_id,
                'role_name' => $role?->name ?? $user?->role,
                'office_id' => $user?->office_id,
                'office_name' => $office?->name ?? $user?->office,
                'user_role' => $role?->name ?? $user?->role,
                'user_office' => $office?->name ?? $user?->office,
                'module' => $data['module'] ?? self::moduleFrom($auditable, $document),
                'action' => self::normalizeAction($action),
                'status' => $data['status'] ?? self::statusFrom($action, $severity),
                'severity' => $severity,
                'description' => $data['description'] ?? null,
                'target_type' => $data['target_type'] ?? ($auditable ? $auditable::class : null),
                'target_id' => $data['target_id'] ?? $auditable?->getKey(),
                'target_label' => $data['target_label'] ?? self::labelFor($auditable, $document),
                'document_type' => $data['document_type'] ?? self::documentType($document),
                'document_id' => $data['document_id'] ?? $document?->getKey(),
                'tracking_number' => $data['tracking_number'] ?? self::trackingNumber($document),
                'document_reference_number' => $data['document_reference_number'] ?? self::documentReferenceNumber($document),
                'route_name' => $data['route_name'] ?? self::routeName(),
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'old_values' => self::sanitize($data['old_values'] ?? null),
                'new_values' => self::sanitize($data['new_values'] ?? null),
                'metadata' => self::sanitize($data['metadata'] ?? self::metadataFrom($data)),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'request_method' => Request::method(),
                'request_url' => Request::fullUrl(),
            ]);
        } catch (Throwable $exception) {
            Log::error('PaperTrail audit logging failed.', [
                'action' => $action,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function modelFrom(mixed $value): ?Model
    {
        return $value instanceof Model ? $value : null;
    }

    private static function documentFrom(?Model $model): ?Model
    {
        if (! $model) {
            return null;
        }

        if ($model instanceof ProcurementDocument) {
            return $model;
        }

        foreach (['document_reference_number', 'tracking_number', 'document_type', 'pr_no', 'resolution_number', 'rfq_number', 'abstract_number', 'po_number', 'inspection_number'] as $attribute) {
            if (array_key_exists($attribute, $model->getAttributes())) {
                return $model;
            }
        }

        return null;
    }

    private static function moduleFrom(?Model $auditable, ?Model $document): string
    {
        if ($document) {
            return self::documentType($document) ?: 'Documents';
        }

        return $auditable ? class_basename($auditable) : 'System';
    }

    private static function statusFrom(string $action, string $severity): string
    {
        $normalized = Str::lower($action);

        if (str_contains($normalized, 'unauthorized') || str_contains($normalized, 'denied')) {
            return 'denied';
        }

        if (str_contains($normalized, 'failed') || str_contains($normalized, 'failure')) {
            return 'failed';
        }

        if (str_contains($normalized, 'skipped')) {
            return 'skipped';
        }

        if (in_array($severity, ['warning', 'critical'], true)) {
            return 'warning';
        }

        return 'success';
    }

    private static function normalizeAction(string $action): string
    {
        return trim($action);
    }

    private static function routeName(): ?string
    {
        try {
            return Request::route()?->getName();
        } catch (Throwable) {
            return null;
        }
    }

    private static function labelFor(?Model $auditable, ?Model $document): ?string
    {
        $model = $document ?? $auditable;

        if (! $model) {
            return null;
        }

        return self::trackingNumber($model)
            ?? $model->title
            ?? $model->name
            ?? $model->user_id
            ?? class_basename($model) . ' #' . $model->getKey();
    }

    private static function documentType(?Model $document): ?string
    {
        if (! $document) {
            return null;
        }

        return $document->document_type
            ?? (method_exists($document, 'documentType') ? $document->documentType() : null)
            ?? class_basename($document);
    }

    private static function trackingNumber(?Model $document): ?string
    {
        if (! $document) {
            return null;
        }

        if (method_exists($document, 'displayNumber')) {
            return $document->displayNumber();
        }

        foreach ([
            'tracking_number',
            'pr_no',
            'resolution_number',
            'rfq_number',
            'abstract_number',
            'po_number',
            'inspection_number',
            'supplemental_app_number',
            'app_number',
        ] as $attribute) {
            if (filled($document->{$attribute} ?? null)) {
                return (string) $document->{$attribute};
            }
        }

        return null;
    }

    private static function documentReferenceNumber(?Model $document): ?string
    {
        if (! $document) {
            return null;
        }

        if (filled($document->document_reference_number ?? null)) {
            return (string) $document->document_reference_number;
        }

        if (filled($document->papertrail_reference_number ?? null)) {
            return (string) $document->papertrail_reference_number;
        }

        return null;
    }

    private static function metadataFrom(array $data): array
    {
        return collect($data)
            ->except([
                'module',
                'description',
                'auditable',
                'target',
                'document',
                'old_values',
                'new_values',
                'severity',
                'status',
                'target_type',
                'target_id',
                'target_label',
                'document_type',
                'document_id',
                'tracking_number',
                'document_reference_number',
                'route_name',
            ])
            ->all();
    }

    private static function sanitize(mixed $values): mixed
    {
        if ($values === null) {
            return null;
        }

        if (! is_array($values)) {
            return $values;
        }

        foreach ($values as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if (self::isSensitiveKey($normalizedKey)) {
                $values[$key] = '[redacted]';
                continue;
            }

            if (self::isLargeContentKey($normalizedKey)) {
                $values[$key] = '[omitted]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::sanitize($value);
            }
        }

        return $values;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($key === $sensitiveKey || str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return str_contains($key, 'password')
            || str_contains($key, 'secret')
            || str_contains($key, 'token');
    }

    private static function isLargeContentKey(string $key): bool
    {
        foreach (self::LARGE_CONTENT_KEYS as $largeKey) {
            if ($key === $largeKey || str_contains($key, $largeKey)) {
                return true;
            }
        }

        return false;
    }
}
