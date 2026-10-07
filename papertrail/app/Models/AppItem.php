<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppItem extends Model
{
    protected $fillable = [
        'app_consolidation_id',
        'source_ppmp_document_id',
        'source_ppmp_item_id',
        'office_id',
        'item_no',
        'general_description',
        'quantity',
        'unit',
        'estimated_unit_cost',
        'estimated_total_cost',
        'procurement_mode',
        'schedule_quarter',
        'category',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
    ];

    public function appConsolidation(): BelongsTo
    {
        return $this->belongsTo(AppConsolidation::class);
    }

    public function sourcePpmpDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class, 'source_ppmp_document_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
