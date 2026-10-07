<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppPpmpSource extends Model
{
    public const STATUS_INCLUDED = 'included';

    protected $fillable = [
        'app_consolidation_id',
        'procurement_document_id',
        'office_id',
        'included_by_user_id',
        'included_at',
        'status',
    ];

    protected $casts = [
        'included_at' => 'datetime',
    ];

    public function appConsolidation(): BelongsTo
    {
        return $this->belongsTo(AppConsolidation::class);
    }

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function includedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'included_by_user_id');
    }
}
