<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_PO;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_FORWARDED_TO_SUPPLIER = 'forwarded_to_supplier';
    public const STATUS_FUND_CERTIFIED = 'fund_certified';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PREPARED = 'prepared';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'procurement_document_id',
        'source_pr_document_id',
        'source_bac_resolution_id',
        'source_abstract_id',
        'po_number',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'po_date',
        'fiscal_year',
        'supplier_name',
        'supplier_address',
        'supplier_contact',
        'mode_of_procurement',
        'place_of_delivery',
        'date_of_delivery',
        'delivery_term',
        'payment_term',
        'delivery_place',
        'delivery_date',
        'delivery_terms',
        'payment_terms',
        'total_amount',
        'total_amount_words',
        'penalty_clause',
        'authorized_official_name',
        'authorized_official_designation',
        'supplier_representative_name',
        'supplier_representative_designation',
        'supplier_conforme_date',
        'fund_available_text',
        'alobs_number',
        'alobs_amount',
        'accountant_name',
        'accountant_designation',
        'status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'issued_by_user_id',
        'issued_at',
        'completed_at',
        'returned_at',
        'cancelled_at',
        'remarks',
        'document_html',
        'document_text',
        'items_json',
        'document_version',
    ];

    protected $casts = [
        'po_date' => 'date',
        'delivery_date' => 'date',
        'submitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'alobs_amount' => 'decimal:2',
        'items_json' => 'array',
        'document_version' => 'integer',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function sourceBacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class, 'source_bac_resolution_id');
    }

    public function sourceAbstract(): BelongsTo
    {
        return $this->belongsTo(AbstractQuotation::class, 'source_abstract_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function inspectionAcceptanceRecords(): HasMany
    {
        return $this->hasMany(InspectionAcceptanceRecord::class)->latest();
    }

    public function latestInspectionAcceptanceRecord(): HasOne
    {
        return $this->hasOne(InspectionAcceptanceRecord::class)->latestOfMany();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function canSubmit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->po_number
            ?: 'PO Draft #' . $this->getKey();
    }
}
