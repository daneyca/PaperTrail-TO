<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SvpChainEvent extends Model
{
    protected $fillable = [
        'svp_procurement_chain_id',
        'document_type',
        'document_id',
        'action',
        'stage',
        'status',
        'from_role',
        'to_role',
        'from_office_id',
        'to_office_id',
        'performed_by_user_id',
        'remarks',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(SvpProcurementChain::class, 'svp_procurement_chain_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
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
