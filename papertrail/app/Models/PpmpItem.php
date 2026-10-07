<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpmpItem extends Model
{
    protected $fillable = [
        'procurement_document_id',
        'ppmp_id',
        'row_order',
        'item_no',
        'general_description',
        'project_type',
        'quantity_size',
        'quantity',
        'unit',
        'estimated_unit_cost',
        'estimated_total_cost',
        'procurement_mode',
        'pre_procurement_conference',
        'start_procurement_activity',
        'end_procurement_activity',
        'expected_delivery_period',
        'source_of_funds',
        'attached_supporting_documents',
        'schedule_quarter',
        'category',
        'remarks',
        'is_bold',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
        'is_bold' => 'boolean',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }
}
