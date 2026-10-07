<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacDeliberationComment extends Model
{
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_PUBLIC_TO_BAC = 'public_to_bac';

    protected $fillable = [
        'bac_deliberation_id',
        'user_id',
        'comment',
        'visibility',
    ];

    public function bacDeliberation(): BelongsTo
    {
        return $this->belongsTo(BacDeliberation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
