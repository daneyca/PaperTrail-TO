<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelayThresholdRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'stage',
        'status',
        'low_risk_days',
        'medium_risk_days',
        'high_risk_days',
        'critical_risk_days',
        'notify_role',
        'notify_office_id',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'low_risk_days' => 'integer',
        'medium_risk_days' => 'integer',
        'high_risk_days' => 'integer',
        'critical_risk_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function notifyOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'notify_office_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
