<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacSecretariatReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_READY_FOR_ROUTING = 'ready_for_routing';

    protected $fillable = [
        'procurement_document_id',
        'reviewed_by_user_id',
        'review_status',
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
