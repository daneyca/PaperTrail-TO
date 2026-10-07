<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRoutingHistory extends Model
{
    protected $fillable = [
        'procurement_document_id',
        'action_by_user_id',
        'from_office_id',
        'to_office_id',
        'action',
        'status_from',
        'status_to',
        'comments',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function procurementDocument(): BelongsTo
    {
        return $this->belongsTo(ProcurementDocument::class);
    }

    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }

    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'to_office_id');
    }
}
