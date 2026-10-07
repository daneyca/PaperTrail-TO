<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_SYSTEM = 'System';
    public const TYPE_END_USER = 'End-User Office';
    public const TYPE_PROCUREMENT = 'Procurement / BAC';
    public const TYPE_FINANCE = 'Finance / Review';
    public const TYPE_APPROVING = 'Approving Office';
    public const TYPE_LEGISLATIVE = 'Legislative Office';

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'status',
        'is_requesting_office',
    ];

    protected $casts = [
        'is_requesting_office' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRequestingOffice(): bool
    {
        return $this->isActive() && (bool) $this->is_requesting_office;
    }

    public function scopeRequesting(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('is_requesting_office', true);
    }
}
