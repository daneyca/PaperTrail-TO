<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignedDocumentSnapshot extends Model
{
    protected $fillable = [
        'electronic_signature_id',
        'document_type',
        'document_id',
        'document_label',
        'tracking_number',
        'html_snapshot',
        'text_snapshot',
        'snapshot_hash',
        'created_by_user_id',
    ];

    public function signature(): BelongsTo
    {
        return $this->belongsTo(ElectronicSignature::class, 'electronic_signature_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
