<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CompetitiveBiddingSupportingItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ATTACHED = 'attached';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_MISSING = 'missing';
    public const STATUS_FOR_CORRECTION = 'for_correction';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        'competitive_bidding_record_id',
        'competitive_bidding_supplier_id',
        'guide_number',
        'requirement_key',
        'display_order',
        'section',
        'phase',
        'requirement_name',
        'document_source',
        'is_required',
        'status',
        'remarks',
        'submitted_by_user_id',
        'submitted_at',
        'source_type',
        'source_id',
        'source_reference',
        'source_status',
        'source_version',
        'checked_by_user_id',
        'checked_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'submitted_at' => 'datetime',
        'source_version' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CompetitiveBiddingRecord::class, 'competitive_bidding_record_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(CompetitiveBiddingSupplier::class, 'competitive_bidding_supplier_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable')
            ->where('status', DocumentAttachment::STATUS_ACTIVE)
            ->latest();
    }

    public function isPostAwardConditional(): bool
    {
        return $this->requirement_key === 'performance_bond';
    }

    public function hasSystemDocument(): bool
    {
        return $this->source_type === CompetitiveBiddingSignatureDocument::class
            && $this->source_id !== null;
    }

    public function hasSubmittedEvidence(): bool
    {
        return $this->attachments()->exists() || $this->hasSystemDocument();
    }

    public function isEffectivelyRequired(): bool
    {
        if (! $this->is_required) {
            return false;
        }

        if (! $this->isPostAwardConditional()) {
            return true;
        }

        return $this->supplier?->review_status === CompetitiveBiddingSupplier::STATUS_AWARDED;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? 'Pending';
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ATTACHED => 'Attached - For Checking',
            self::STATUS_VERIFIED => 'Verified',
            self::STATUS_MISSING => 'Missing',
            self::STATUS_FOR_CORRECTION => 'For Correction',
            self::STATUS_NOT_APPLICABLE => 'Not Applicable',
        ];
    }
}
