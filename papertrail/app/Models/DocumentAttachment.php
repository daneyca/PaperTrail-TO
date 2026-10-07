<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentAttachment extends Model
{
    use SoftDeletes;

    public const CATEGORY_SUPPORTING_DOCUMENT = 'supporting_document';
    public const CATEGORY_SCANNED_DOCUMENT = 'scanned_document';
    public const CATEGORY_QUOTATION = 'quotation';
    public const CATEGORY_ELIGIBILITY_DOCUMENT = 'eligibility_document';
    public const CATEGORY_BAC_DOCUMENT = 'bac_document';
    public const CATEGORY_SIGNED_DOCUMENT = 'signed_document';
    public const CATEGORY_OTHER = 'other';

    public const OCR_PENDING = 'pending';
    public const OCR_NOT_REQUIRED = 'not_required';
    public const OCR_QUEUED = 'queued';
    public const OCR_PROCESSING = 'processing';
    public const OCR_COMPLETED = 'completed';
    public const OCR_FAILED = 'failed';

    public const AI_PENDING = 'pending';
    public const AI_QUEUED = 'queued';
    public const AI_COMPLETED = 'completed';
    public const AI_FAILED = 'failed';
    public const AI_SKIPPED = 'skipped';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'attachment_uuid',
        'attachable_type',
        'attachable_id',
        'procurement_document_id',
        'document_type',
        'document_id',
        'tracking_number',
        'office_id',
        'office_name',
        'uploaded_by_user_id',
        'original_name',
        'original_filename',
        'stored_filename',
        'file_path',
        'disk',
        'mime_type',
        'file_extension',
        'size',
        'file_size',
        'file_hash',
        'description',
        'document_section',
        'attachment_category',
        'ocr_status',
        'ocr_text',
        'extracted_metadata',
        'ai_analysis_status',
        'is_required',
        'is_confidential',
        'status',
        'deleted_by_user_id',
    ];

    protected $casts = [
        'extracted_metadata' => 'array',
        'is_required' => 'boolean',
        'is_confidential' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DocumentAttachment $attachment) {
            if (! $attachment->attachment_uuid) {
                $attachment->attachment_uuid = (string) Str::uuid();
            }
        });
    }

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function aiMetadataExtractions(): HasMany
    {
        return $this->hasMany(AiDocumentMetadata::class, 'attachment_id')->latest();
    }

    public function latestAiMetadataExtraction(): HasOne
    {
        return $this->hasOne(AiDocumentMetadata::class, 'attachment_id')->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForDocument(Builder $query, string $documentType, Model $document): Builder
    {
        $normalizedType = Str::of($documentType)->replace('-', '_')->lower()->toString();

        return $query->where(function (Builder $builder) use ($normalizedType, $document) {
            $builder->where(function (Builder $morph) use ($document) {
                $morph->where('attachable_type', $document::class)
                    ->where('attachable_id', $document->getKey());
            })
                ->orWhere(function (Builder $generic) use ($normalizedType, $document) {
                    $generic->where('document_type', $normalizedType)
                        ->where('document_id', $document->getKey());
                });

            if ($document instanceof ProcurementDocument) {
                $builder->orWhere('procurement_document_id', $document->getKey());
            }
        });
    }

    public function displayName(): string
    {
        return $this->original_filename
            ?: $this->original_name
            ?: $this->stored_filename
            ?: 'Attachment #' . $this->getKey();
    }

    public function fileSizeHuman(): string
    {
        $bytes = (int) ($this->file_size ?: $this->size ?: 0);

        if ($bytes <= 0) {
            return '0 KB';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/')
            || in_array(strtolower((string) $this->file_extension), ['jpg', 'jpeg', 'png'], true);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf'
            || strtolower((string) $this->file_extension) === 'pdf';
    }

    public function isOcrReady(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->isPdf() || $this->isImage());
    }

    public function storageDisk(): string
    {
        return $this->disk ?: 'local';
    }

    public function downloadUrl(): string
    {
        return route('document-attachments.download', $this);
    }

    public function viewUrl(): string
    {
        return route('document-attachments.view', $this);
    }

    public function url(): string
    {
        return $this->viewUrl();
    }

    public function readableStorageDisk(): string
    {
        $disk = $this->storageDisk();

        if ($this->file_path && Storage::disk($disk)->exists($this->file_path)) {
            return $disk;
        }

        if ($this->file_path && Storage::disk('public')->exists($this->file_path)) {
            return 'public';
        }

        return $disk;
    }
}
