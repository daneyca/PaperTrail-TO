<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitiveBiddingSupplier extends Model
{
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_FOR_DISQUALIFICATION = 'for_disqualification';
    public const STATUS_DISQUALIFIED = 'disqualified';
    public const STATUS_AWARDED = 'awarded';

    protected $fillable = [
        'competitive_bidding_record_id',
        'supplier_name',
        'contact_person',
        'email',
        'review_status',
        'decision_remarks',
        'created_by_user_id',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(CompetitiveBiddingRecord::class, 'competitive_bidding_record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function supportingItems(): HasMany
    {
        return $this->hasMany(CompetitiveBiddingSupportingItem::class)->orderBy('display_order');
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->review_status] ?? 'Under Review';
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_UNDER_REVIEW => 'Under Review',
            self::STATUS_QUALIFIED => 'Qualified',
            self::STATUS_FOR_DISQUALIFICATION => 'For Disqualification Review',
            self::STATUS_DISQUALIFIED => 'Disqualified',
            self::STATUS_AWARDED => 'Awarded',
        ];
    }
}
