<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApproval extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_DEFERRED = 'deferred';

    public const DECISION_APPROVED = 'approved';
    public const DECISION_RETURNED_TO_BAC_CHAIR = 'returned_to_bac_chair';
    public const DECISION_RETURNED_TO_BAC_SECRETARIAT = 'returned_to_bac_secretariat';
    public const DECISION_RETURNED_TO_ACCOUNTING = 'returned_to_accounting';
    public const DECISION_RETURNED_TO_REQUESTING_OFFICE = 'returned_to_requesting_office';

    protected $fillable = [
        'procurement_document_id',
        'approved_by_user_id',
        'approval_status',
        'decision',
        'remarks',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
