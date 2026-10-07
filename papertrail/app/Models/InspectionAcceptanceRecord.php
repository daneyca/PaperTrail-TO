<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionAcceptanceRecord extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_IA;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_INSPECTED = 'inspected';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'purchase_order_id',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'source_pr_document_id',
        'office_id',
        'inspected_by_user_id',
        'accepted_by_user_id',
        'status',
        'inspection_date',
        'acceptance_date',
        'delivery_receipt_number',
        'invoice_number',
        'quantity_condition',
        'quality_condition',
        'findings',
        'remarks',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'acceptance_date' => 'date',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: 'IA #' . $this->getKey();
    }
}
