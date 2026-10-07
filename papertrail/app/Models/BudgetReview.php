<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_BUDGET_AVAILABLE = 'budget_available';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_INSUFFICIENT_FUNDS = 'insufficient_funds';

    protected $fillable = [
        'procurement_document_id',
        'reviewed_by_user_id',
        'review_status',
        'requested_amount',
        'available_amount',
        'fund_source',
        'appropriation_code',
        'responsibility_center',
        'remarks',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'available_amount' => 'decimal:2',
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
