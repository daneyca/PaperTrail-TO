<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature_type',
        'document_type',
        'rule_name',
        'prompt_instruction',
        'expected_output_schema',
        'system_note',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'expected_output_schema' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
