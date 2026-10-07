<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SignatureRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_EXPIRED = 'expired';

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_NOTIFIED,
        self::STATUS_VIEWED,
    ];

    protected $fillable = [
        'request_uuid',
        'document_type',
        'document_id',
        'document_label',
        'tracking_number',
        'signatory_slot',
        'signatory_label',
        'signing_order',
        'signing_mode',
        'is_required',
        'requested_by_user_id',
        'requested_to_user_id',
        'requested_to_role',
        'requested_to_office_id',
        'status',
        'notification_sent_at',
        'email_sent_at',
        'viewed_at',
        'signed_at',
        'declined_at',
        'cancelled_at',
        'due_at',
        'remarks',
        'metadata',
    ];

    protected $casts = [
        'signing_order' => 'integer',
        'is_required' => 'boolean',
        'notification_sent_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'due_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requestedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_to_user_id');
    }

    public function requestedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'requested_to_office_id');
    }

    public function electronicSignature(): HasOne
    {
        return $this->hasOne(ElectronicSignature::class)->latestOfMany();
    }

    public function scopeForDocument(Builder $query, string $documentType, int|string $documentId): Builder
    {
        return $query->where('document_type', $documentType)
            ->where('document_id', $documentId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
