<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'event_uuid',
        'user_id',
        'user_identifier',
        'user_name',
        'role_id',
        'role_name',
        'office_id',
        'office_name',
        'user_role',
        'user_office',
        'module',
        'action',
        'status',
        'severity',
        'description',
        'target_type',
        'target_id',
        'target_label',
        'document_type',
        'document_id',
        'tracking_number',
        'document_reference_number',
        'route_name',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
