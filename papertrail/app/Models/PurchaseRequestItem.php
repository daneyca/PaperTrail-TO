<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'procurement_document_id',
        'app_item_id',
        'item_no',
        'item_description',
        'description',
        'quantity',
        'unit',
        'unit_of_issue',
        'stock_no',
        'estimated_unit_cost',
        'estimated_total_cost',
        'estimated_cost',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function appItem(): BelongsTo
    {
        return $this->belongsTo(AppItem::class);
    }
}
