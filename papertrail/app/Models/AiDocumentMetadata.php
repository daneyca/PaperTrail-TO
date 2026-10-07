<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDocumentMetadata extends Model
{
    use HasFactory;

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $table = 'ai_document_metadata';

    protected $fillable = [
        'document_type',
        'document_id',
        'attachment_id',
        'extracted_metadata',
        'classification_result',
        'confidence_score',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'ai_response',
        'created_by_user_id',
    ];

    protected $casts = [
        'extracted_metadata' => 'array',
        'classification_result' => 'array',
        'confidence_score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(DocumentAttachment::class, 'attachment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
