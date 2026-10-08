<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupplierSignatureRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VIEWED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'request_uuid',
        'public_token',
        'document_type',
        'document_title',
        'tracking_number',
        'supplier_name',
        'supplier_email',
        'supplier_contact',
        'signatory_name',
        'signatory_position',
        'status',
        'instructions',
        'source_document_path',
        'source_document_name',
        'source_document_mime',
        'source_document_size',
        'source_document_hash',
        'source_document_disk',
        'signed_document_path',
        'signed_document_name',
        'signed_document_mime',
        'signed_document_size',
        'signed_document_hash',
        'signed_document_disk',
        'requested_by_user_id',
        'accepted_by_user_id',
        'requested_at',
        'viewed_at',
        'submitted_at',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
        'expires_at',
        'signer_ip_address',
        'signer_user_agent',
        'supplier_remarks',
        'bac_remarks',
        'consent_text',
        'metadata',
    ];

    protected $casts = [
        'source_document_size' => 'integer',
        'signed_document_size' => 'integer',
        'requested_at' => 'datetime',
        'viewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'public_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupplierSignatureRequest $request) {
            $request->request_uuid ??= (string) Str::uuid();
            $request->public_token ??= Str::random(64);
            $request->requested_at ??= now();
            $request->status ??= self::STATUS_PENDING;
        });
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search) {
            $builder->where('document_title', 'like', "%{$search}%")
                ->orWhere('tracking_number', 'like', "%{$search}%")
                ->orWhere('supplier_name', 'like', "%{$search}%")
                ->orWhere('supplier_email', 'like', "%{$search}%")
                ->orWhere('signatory_name', 'like', "%{$search}%");
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isOpenForSupplier(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true)
            && ! $this->isExpired();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACCEPTED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true);
    }

    public function hasSourceDocument(): bool
    {
        return filled($this->source_document_path);
    }

    public function hasSignedDocument(): bool
    {
        return filled($this->signed_document_path);
    }

    public function sourceDocumentName(): string
    {
        return $this->source_document_name ?: 'source-document';
    }

    public function signedDocumentName(): string
    {
        return $this->signed_document_name ?: 'signed-document';
    }

    public function statusLabel(): string
    {
        return str($this->status ?: self::STATUS_PENDING)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function publicUrl(): string
    {
        return route('supplier-signing.show', $this->public_token);
    }
}
