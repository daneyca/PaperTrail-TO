<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestValidation extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_UNDER_VALIDATION = 'under_validation';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_ROUTED_TO_BUDGET = 'routed_to_budget';

    protected $fillable = [
        'procurement_document_id',
        'validated_by_user_id',
        'validation_status',
        'app_reference_checked',
        'attachments_checked',
        'item_details_checked',
        'remarks',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'app_reference_checked' => 'boolean',
        'attachments_checked' => 'boolean',
        'item_details_checked' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
