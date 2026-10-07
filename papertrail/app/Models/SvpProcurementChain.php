<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SvpProcurementChain extends Model
{
    public const STAGE_PURCHASE_REQUEST = 'Purchase Request';
    public const STAGE_BAC_RESOLUTION = 'BAC Resolution';
    public const STAGE_POSTING = 'Posting';
    public const STAGE_POSTING_COMPLETED = 'Posting Period Completed';
    public const STAGE_RFQ = 'RFQ';
    public const STAGE_SUPPLIER_QUOTATIONS = 'Supplier Quotations';
    public const STAGE_ABSTRACT = 'Abstract';
    public const STAGE_PURCHASE_ORDER = 'Purchase Order';
    public const STAGE_DELIVERY = 'Delivery';
    public const STAGE_INSPECTION = 'Inspection / Acceptance';
    public const STAGE_ACCOUNTING_SUBMISSION = 'Accounting Submission';
    public const STAGE_COMPLETED = 'Completed';

    public static function workflowStages(): array
    {
        return [
            self::STAGE_PURCHASE_REQUEST,
            self::STAGE_BAC_RESOLUTION,
            self::STAGE_POSTING,
            self::STAGE_POSTING_COMPLETED,
            self::STAGE_RFQ,
            self::STAGE_SUPPLIER_QUOTATIONS,
            self::STAGE_ABSTRACT,
            self::STAGE_PURCHASE_ORDER,
            self::STAGE_DELIVERY,
            self::STAGE_INSPECTION,
            self::STAGE_ACCOUNTING_SUBMISSION,
            self::STAGE_COMPLETED,
        ];
    }

    protected $fillable = [
        'chain_number',
        'tracking_number',
        'office_id',
        'office_name',
        'source_pr_document_id',
        'bac_resolution_id',
        'rfq_id',
        'abstract_id',
        'purchase_order_id',
        'inspection_id',
        'current_stage',
        'current_status',
        'procurement_mode',
        'total_amount',
        'created_by_user_id',
        'updated_by_user_id',
        'completed_at',
        'remarks',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function sourcePrDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_pr_document_id');
    }

    public function bacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function abstract(): BelongsTo
    {
        return $this->belongsTo(AbstractQuotation::class, 'abstract_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(InspectionAcceptanceRecord::class, 'inspection_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SvpChainEvent::class)->oldest();
    }

    public function postingRecords(): HasMany
    {
        return $this->hasMany(SvpPostingRecord::class);
    }

    public function latestPostingRecord(): HasOne
    {
        return $this->hasOne(SvpPostingRecord::class)->latestOfMany();
    }
}
