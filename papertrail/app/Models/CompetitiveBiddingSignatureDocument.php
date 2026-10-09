<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CompetitiveBiddingSignatureDocument extends Model
{
    public const STATUS_PENDING_SIGNATURES = 'pending_signatures';
    public const STATUS_PARTIALLY_SIGNED = 'partially_signed';
    public const STATUS_FULLY_SIGNED = 'fully_signed';
    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'document_uuid',
        'template_key',
        'document_title',
        'tracking_number',
        'document_html',
        'signatories',
        'status',
        'prepared_by_user_id',
        'submitted_at',
    ];

    protected $casts = [
        'signatories' => 'array',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CompetitiveBiddingSignatureDocument $document): void {
            $document->document_uuid ??= (string) Str::uuid();
            $document->status ??= self::STATUS_PENDING_SIGNATURES;
            $document->submitted_at ??= now();
        });
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(SignatureRequest::class, 'document_id')
            ->where('document_type', 'competitive_bidding_document');
    }

    public function displayNumber(): string
    {
        return $this->tracking_number ?: 'CB-' . str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return str($this->status ?: self::STATUS_PENDING_SIGNATURES)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
