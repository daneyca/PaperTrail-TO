<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'current_status',
        'next_status',
        'from_role',
        'to_role',
        'from_office_id',
        'to_office_id',
        'route_label',
        'rule_description',
        'requires_signature',
        'requires_attachment_check',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'requires_signature' => 'boolean',
        'requires_attachment_check' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'to_office_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
