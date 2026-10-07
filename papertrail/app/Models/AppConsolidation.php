<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppConsolidation extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_APP;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONSOLIDATED = 'consolidated';
    public const STATUS_SUBMITTED_FOR_APPROVAL = 'submitted_for_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'app_number',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'fiscal_year',
        'title',
        'description',
        'prepared_by_user_id',
        'procurement_document_id',
        'status',
        'total_amount',
        'remarks',
        'consolidated_at',
        'submitted_for_approval_at',
        'approved_at',
        'cancelled_at',
    ];

    protected $casts = [
        'consolidated_at' => 'datetime',
        'submitted_for_approval_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function appItems(): HasMany
    {
        return $this->hasMany(AppItem::class);
    }

    public function ppmpSources(): HasMany
    {
        return $this->hasMany(AppPpmpSource::class);
    }

    public function procurementDocuments(): BelongsToMany
    {
        return $this->belongsToMany(ProcurementDocument::class, 'app_ppmp_sources')
            ->withPivot(['office_id', 'included_by_user_id', 'included_at', 'status'])
            ->withTimestamps();
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->app_number
            ?: 'APP Draft #' . $this->getKey();
    }
}
