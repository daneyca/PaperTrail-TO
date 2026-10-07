<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplementalApp extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CREATED = 'created';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_LINKED_TO_PR = 'linked_to_pr';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'supplemental_app_number',
        'fiscal_year',
        'source_pr_document_id',
        'requesting_office_id',
        'requesting_office_name',
        'title',
        'purpose',
        'justification',
        'total_amount',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'accepted_by_user_id',
        'accepted_at',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function requestingOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'requesting_office_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplementalAppItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CREATED], true);
    }

    public function isAccepted(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_LINKED_TO_PR], true);
    }
}
