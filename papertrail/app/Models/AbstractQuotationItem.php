<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbstractQuotationItem extends Model
{
    protected $table = 'abstract_items';

    protected $fillable = [
        'abstract_id',
        'item_no',
        'name_of_goods_services',
        'quantity',
        'unit_of_measure',
        'supplier_1_amount',
        'supplier_2_amount',
        'supplier_3_amount',
        'supplier_4_amount',
        'supplier_5_amount',
        'total_lowest_price',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'supplier_1_amount' => 'decimal:2',
        'supplier_2_amount' => 'decimal:2',
        'supplier_3_amount' => 'decimal:2',
        'supplier_4_amount' => 'decimal:2',
        'supplier_5_amount' => 'decimal:2',
        'total_lowest_price' => 'decimal:2',
    ];

    public function abstractQuotation(): BelongsTo
    {
        return $this->belongsTo(AbstractQuotation::class, 'abstract_id');
    }
}
