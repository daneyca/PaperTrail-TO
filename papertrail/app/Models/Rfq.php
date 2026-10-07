<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_RFQ;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'source_pr_document_id',
        'source_bac_resolution_id',
        'rfq_number',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'rfq_date',
        'supplier_name',
        'supplier_address',
        'procurement_officer_name',
        'procurement_officer_designation',
        'abc_amount',
        'purpose',
        'delivery_period',
        'warranty_text',
        'price_validity_text',
        'philgeps_requirement_text',
        'mayors_permit_requirement_text',
        'document_html',
        'document_text',
        'items_json',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'remarks',
    ];

    protected $casts = [
        'rfq_date' => 'date',
        'abc_amount' => 'decimal:2',
        'items_json' => 'array',
        'submitted_at' => 'datetime',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
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
        return $this->hasMany(RfqItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function abstracts(): HasMany
    {
        return $this->hasMany(AbstractQuotation::class, 'source_rfq_id');
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
            ?: $this->rfq_number
            ?: 'RFQ Draft #' . $this->getKey();
    }
}
