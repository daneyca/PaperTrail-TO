<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateImport extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_IMPORTED_AS_DRAFT = 'imported_as_draft';
    public const STATUS_MANUAL_REVIEW = 'manual_review';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'document_type',
        'uploaded_by',
        'office_id',
        'original_name',
        'stored_name',
        'file_path',
        'file_format',
        'file_size',
        'import_status',
        'imported_document_id',
        'imported_document_type',
        'validation_errors',
        'notes',
    ];

    protected $casts = [
        'validation_errors' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
