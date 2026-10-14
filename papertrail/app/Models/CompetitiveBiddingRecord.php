<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitiveBiddingRecord extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'reference_number',
        'project_title',
        'approved_budget',
        'bid_opening_date',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'approved_budget' => 'decimal:2',
        'bid_opening_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(CompetitiveBiddingSupplier::class)->orderBy('supplier_name');
    }

    public function supportingItems(): HasMany
    {
        return $this->hasMany(CompetitiveBiddingSupportingItem::class)->orderBy('display_order');
    }

    public function projectItems(): HasMany
    {
        return $this->supportingItems()->whereNull('competitive_bidding_supplier_id');
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_COMPLETED ? 'Completed' : 'Open';
    }
}
