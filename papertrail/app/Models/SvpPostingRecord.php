<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SvpPostingRecord extends Model
{
    public const STATUS_PENDING_POSTING = 'pending_posting';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_DRAFT = self::STATUS_PENDING_POSTING;
    public const STATUS_COMPLETED = self::STATUS_POSTED;

    protected $fillable = [
        'svp_procurement_chain_id',
        'source_pr_document_id',
        'created_by_user_id',
        'completed_by_user_id',
        'pr_reference',
        'bac_resolution_reference',
        'procurement_title',
        'requesting_office',
        'procurement_method',
        'posting_platform',
        'philgeps_reference_number',
        'approved_budget',
        'posting_date',
        'closing_date',
        'status',
        'remarks',
        'posted_at',
        'completed_at',
    ];

    protected $casts = [
        'approved_budget' => 'decimal:2',
        'posting_date' => 'date',
        'closing_date' => 'date',
        'posted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(SvpProcurementChain::class, 'svp_procurement_chain_id');
    }

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    public function displayNumber(): string
    {
        return 'POST-' . str_pad((string) $this->getKey(), 5, '0', STR_PAD_LEFT);
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, [self::STATUS_POSTED, self::STATUS_CLOSED], true);
    }
}
