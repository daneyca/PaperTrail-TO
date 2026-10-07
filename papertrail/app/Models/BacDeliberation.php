<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BacDeliberation extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'procurement_document_id',
        'deliberation_number',
        'title',
        'agenda',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by_user_id',
        'chair_user_id',
        'remarks',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BacDeliberationParticipant::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BacDeliberationComment::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_ONGOING], true);
    }
}
