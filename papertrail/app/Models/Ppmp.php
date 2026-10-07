<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ppmp extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'ppmp_no',
        'fiscal_year',
        'end_user_unit',
        'plan_type',
        'status',
        'office_id',
        'office_name',
        'prepared_by_name',
        'prepared_by_position',
        'submitted_by_name',
        'submitted_by_position',
        'prepared_date',
        'submitted_date',
        'total_budget',
        'created_by',
    ];

    protected $casts = [
        'prepared_date' => 'date',
        'submitted_date' => 'date',
        'total_budget' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class, 'ppmp_id')->orderBy('row_order')->orderBy('id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    public function displayNumber(): string
    {
        return $this->ppmp_no ?: 'PPMP Draft #' . $this->getKey();
    }
}
