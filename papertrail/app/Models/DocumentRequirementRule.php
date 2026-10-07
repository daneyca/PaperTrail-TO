<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirementRule extends Model
{
    use HasFactory;

    public const TYPE_FIELD = 'field';
    public const TYPE_ATTACHMENT = 'attachment';
    public const TYPE_AMOUNT = 'amount';
    public const TYPE_SCHEDULE = 'schedule';
    public const TYPE_SIGNATORY = 'signatory';
    public const TYPE_WORKFLOW = 'workflow';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'document_type',
        'rule_name',
        'requirement_type',
        'field_key',
        'attachment_category',
        'description',
        'is_required',
        'severity',
        'applies_to_status',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
