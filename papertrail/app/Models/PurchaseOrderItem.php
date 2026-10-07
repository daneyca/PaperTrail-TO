<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'source_pr_item_id',
        'item_no',
        'item_description',
        'description',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'sort_order',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function sourcePrItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'source_pr_item_id');
    }
}
