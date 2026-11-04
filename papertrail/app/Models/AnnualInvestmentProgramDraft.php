<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualInvestmentProgramDraft extends Model
{
    protected $fillable = ['user_id', 'payload', 'revision'];

    protected $casts = [
        'payload' => 'array',
        'revision' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
