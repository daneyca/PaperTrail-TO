<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacResolutionItem extends Model
{
    protected $fillable = [
        'bac_resolution_id',
        'source_pr_item_id',
        'item_no',
        'description',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function bacResolution(): BelongsTo
    {
        return $this->belongsTo(BacResolution::class);
    }

    public function sourcePrItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'source_pr_item_id');
    }
}
