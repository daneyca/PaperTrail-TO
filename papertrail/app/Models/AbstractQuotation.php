<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbstractQuotation extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_AOQ;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_READY_FOR_PO = 'ready_for_po';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'abstracts';

    protected $fillable = [
        'source_pr_document_id',
        'source_rfq_id',
        'source_bac_resolution_id',
        'abstract_number',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'abstract_date',
        'project_name',
        'implementing_office',
        'abc_amount',
        'purpose',
        'supplier_1_name',
        'supplier_2_name',
        'supplier_3_name',
        'supplier_4_name',
        'supplier_5_name',
        'lowest_supplier_name',
        'lowest_total_amount',
        'document_html',
        'document_text',
        'items_json',
        'suppliers_json',
        'awards_json',
        'committee_json',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'remarks',
    ];

    protected $casts = [
        'abstract_date' => 'date',
        'abc_amount' => 'decimal:2',
        'lowest_total_amount' => 'decimal:2',
        'items_json' => 'array',
        'suppliers_json' => 'array',
        'awards_json' => 'array',
        'committee_json' => 'array',
        'submitted_at' => 'datetime',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function sourceRfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'source_rfq_id');
    }

    public function sourceBacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class, 'source_bac_resolution_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AbstractQuotationItem::class, 'abstract_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->abstract_number
            ?: 'AOQ Draft #' . $this->getKey();
    }
}
