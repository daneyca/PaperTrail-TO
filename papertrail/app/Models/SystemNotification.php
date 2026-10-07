<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SystemNotification extends Model
{
    public const TYPE_INFO = 'info';
    public const TYPE_WARNING = 'warning';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'module',
        'related_type',
        'related_id',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
