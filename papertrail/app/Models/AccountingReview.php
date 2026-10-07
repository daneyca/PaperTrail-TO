<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_ACCOUNTING_VERIFIED = 'accounting_verified';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_NON_COMPLIANT = 'non_compliant';

    protected $fillable = [
        'procurement_document_id',
        'reviewed_by_user_id',
        'review_status',
        'accounting_reference_no',
        'account_code',
        'object_code',
        'responsibility_center',
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
