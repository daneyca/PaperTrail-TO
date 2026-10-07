<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplementalAppItem extends Model
{
    protected $fillable = [
        'supplemental_app_id',
        'source_pr_item_id',
        'item_no',
        'description',
        'quantity',
        'unit',
        'estimated_unit_cost',
        'estimated_total_cost',
        'remarks',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_total_cost' => 'decimal:2',
    ];

    public function supplementalApp(): BelongsTo
    {
        return $this->belongsTo(SupplementalApp::class);
    }

    public function sourcePrItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'source_pr_item_id');
    }
}
