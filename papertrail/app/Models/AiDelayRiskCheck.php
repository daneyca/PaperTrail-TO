<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDelayRiskCheck extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_CRITICAL = 'CRITICAL';

    protected $fillable = [
        'document_type',
        'document_id',
        'tracking_number',
        'current_status',
        'current_holder',
        'risk_level',
        'delay_days',
        'risk_factors',
        'recommendations',
        'ai_response',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'risk_factors' => 'array',
        'delay_days' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
