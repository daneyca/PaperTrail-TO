<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'group',
        'label',
        'value',
        'type',
        'options',
        'description',
        'is_public',
        'is_locked',
        'sort_order',
        'updated_by_user_id',
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
        'is_locked' => 'boolean',
    ];
}
