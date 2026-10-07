<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentCheck extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'document_type',
        'document_id',
        'document_tracking_number',
        'completeness_score',
        'status',
        'missing_requirements',
        'warnings',
        'recommendations',
        'ai_response',
    ];

    protected $casts = [
        'missing_requirements' => 'array',
        'warnings' => 'array',
        'completeness_score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
