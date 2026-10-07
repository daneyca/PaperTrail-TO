<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSummaryReport extends Model
{
    protected $fillable = [
        'report_period',
        'summary_type',
        'summary_data',
        'ai_summary',
        'generated_by_user_id',
    ];

    protected $casts = [
        'summary_data' => 'array',
    ];

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
