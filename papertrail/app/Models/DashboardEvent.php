<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardEvent extends Model
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_OFFICE = 'office';
    public const VISIBILITY_ROLE = 'role';
    public const VISIBILITY_ALL = 'all';

    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_APPROVAL = 'approval';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'event_time',
        'event_type',
        'visibility',
        'office_id',
        'role_id',
        'related_document_type',
        'related_document_id',
        'created_by',
        'color',
        'is_completed',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'datetime:H:i',
        'is_completed' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder->where('visibility', self::VISIBILITY_ALL)
                ->orWhere(function (Builder $private) use ($user): void {
                    $private->where('visibility', self::VISIBILITY_PRIVATE)
                        ->where('created_by', $user->id);
                })
                ->orWhere(function (Builder $office) use ($user): void {
                    $office->where('visibility', self::VISIBILITY_OFFICE)
                        ->where('office_id', $user->office_id);
                })
                ->orWhere(function (Builder $role) use ($user): void {
                    $role->where('visibility', self::VISIBILITY_ROLE)
                        ->where('role_id', $user->role_id);
                });
        });
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->isAdmin() || (int) $this->created_by === (int) $user->id;
    }
}
