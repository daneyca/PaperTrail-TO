<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacChairReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RETURNED = 'returned';

    public const DECISION_CONFIRM_FOR_APPROVING_AUTHORITY = 'confirm_for_approving_authority';
    public const DECISION_RETURN_TO_BAC_MEMBER = 'return_to_bac_member';
    public const DECISION_RETURN_TO_BAC_SECRETARIAT = 'return_to_bac_secretariat';
    public const DECISION_RETURN_TO_ACCOUNTING = 'return_to_accounting';
    public const DECISION_RETURN_TO_REQUESTING_OFFICE = 'return_to_requesting_office';

    protected $fillable = [
        'procurement_document_id',
        'reviewed_by_user_id',
        'review_status',
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
