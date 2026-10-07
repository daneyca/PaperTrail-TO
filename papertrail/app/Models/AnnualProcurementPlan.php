<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualProcurementPlan extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_APP;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONSOLIDATED = 'consolidated';
    public const STATUS_SUBMITTED = 'submitted';
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
        'app_no',
        'fiscal_year',
        'municipality',
        'province',
        'plan_type',
        'update_version_no',
        'title',
        'office_id',
        'office_name',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'approved_by_user_id',
        'created_by',
        'submitted_at',
        'approved_at',
        'returned_at',
        'return_reason',
        'prepared_by_name',
        'prepared_by_position',
        'prepared_by_office',
        'recommended_by_name',
        'recommended_by_position',
        'recommended_by_office',
        'approved_by_name',
        'approved_by_position',
        'approved_by_office',
        'prepared_date',
        'recommended_date',
        'approved_date',
        'total_epa_budget',
        'total_cse_budget',
        'total_estimated_budget',
        'total_personal_outlay',
        'total_mooe',
        'total_co',
        'document_html',
        'document_text',
        'items_json',
        'signatories_json',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
        'prepared_date' => 'date',
        'recommended_date' => 'date',
        'approved_date' => 'date',
        'total_epa_budget' => 'decimal:2',
        'total_cse_budget' => 'decimal:2',
        'items_json' => 'array',
        'signatories_json' => 'array',
        'total_estimated_budget' => 'decimal:2',
        'total_personal_outlay' => 'decimal:2',
        'total_mooe' => 'decimal:2',
        'total_co' => 'decimal:2',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AnnualProcurementPlanItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AnnualProcurementPlanVersion::class)
            ->orderByDesc('version_no');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CONSOLIDATED, self::STATUS_RETURNED], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function canApprove(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CONSOLIDATED], true);
    }

    public function canReturn(): bool
    {
        return $this->canApprove();
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->app_no
            ?: ($this->app_number ?: 'APP Draft #' . $this->getKey());
    }

    public function documentType(): string
    {
        return 'Annual Procurement Plan';
    }
}
