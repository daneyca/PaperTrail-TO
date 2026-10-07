<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentReferenceNumber;
use App\Services\DocumentReferenceNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacResolution extends Model
{
    use HasDocumentReferenceNumber;

    protected static string $documentReferenceType = DocumentReferenceNumberService::TYPE_BAC;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED_TO_BAC_CHAIR = 'submitted_to_bac_chair';
    public const STATUS_RETURNED_BY_BAC_CHAIR = 'returned_by_bac_chair';
    public const STATUS_CONFIRMED_BY_BAC_CHAIR = 'confirmed_by_bac_chair';
    public const STATUS_FORWARDED_TO_HOPE = 'forwarded_to_hope';
    public const STATUS_APPROVED_BY_HOPE = 'approved_by_hope';
    public const STATUS_RETURNED_BY_HOPE = 'returned_by_hope';
    public const STATUS_RETURNED_TO_END_USER = 'returned_to_end_user';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'resolution_number',
        'document_reference_number',
        'document_type',
        'sequence_number',
        'created_year',
        'created_month',
        'resolution_series',
        'fiscal_year',
        'resolution_date',
        'source_pr_document_id',
        'procurement_document_id',
        'title',
        'project_title',
        'contractor_name',
        'procurement_mode',
        'supplier_name',
        'supplier_address',
        'supplier_contact',
        'abc_amount',
        'refund_amount',
        'requesting_office_name',
        'pr_number',
        'total_amount',
        'total_amount_words',
        'philgeps_reference_no',
        'solicitation_no',
        'status',
        'signature_status',
        'prepared_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'returned_at',
        'remarks',
        'document_html',
        'document_text',
        'document_version',
        'bac_chair_confirmed_by_user_id',
        'bac_chair_confirmed_at',
        'bac_chair_remarks',
        'forwarded_to_hope_by_user_id',
        'forwarded_to_hope_at',
        'body_json',
        'header_lines',
        'whereas_clauses',
        'resolved_clauses',
        'signatories',
        'approval_details',
        'approval_signatory',
    ];

    protected $casts = [
        'resolution_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'returned_at' => 'datetime',
        'bac_chair_confirmed_at' => 'datetime',
        'forwarded_to_hope_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'abc_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'document_version' => 'integer',
        'body_json' => 'array',
        'header_lines' => 'array',
        'whereas_clauses' => 'array',
        'resolved_clauses' => 'array',
        'signatories' => 'array',
        'approval_details' => 'array',
        'approval_signatory' => 'array',
        'sequence_number' => 'integer',
        'created_year' => 'integer',
        'created_month' => 'integer',
    ];

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'procurement_document_id');
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

    public function bacChairConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bac_chair_confirmed_by_user_id');
    }

    public function forwardedToHopeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_to_hope_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BacResolutionItem::class);
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class, 'source_bac_resolution_id');
    }

    public function electronicSignatures(): HasMany
    {
        return $this->hasMany(ElectronicSignature::class, 'document_id')
            ->where('document_type', 'bac_resolution');
    }

    public function signedBacChairSignature(): HasMany
    {
        return $this->electronicSignatures()
            ->where('signature_action', 'confirmed')
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED);
    }

    public function isEditable(): bool
    {
        if ($this->electronicSignatures()
            ->where('signature_status', ElectronicSignature::STATUS_SIGNED)
            ->exists()) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_RETURNED,
            self::STATUS_RETURNED_BY_BAC_CHAIR,
            self::STATUS_RETURNED_BY_HOPE,
        ], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function displayNumber(): string
    {
        return $this->document_reference_number
            ?: $this->resolution_number
            ?: 'BAC Resolution #' . $this->getKey();
    }
}
