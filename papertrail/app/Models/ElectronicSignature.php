<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ElectronicSignature extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_INVALIDATED = 'invalidated';

    protected $fillable = [
        'signature_uuid',
        'signature_code',
        'signature_request_id',
        'document_type',
        'document_id',
        'document_label',
        'tracking_number',
        'signer_user_id',
        'signer_user_identifier',
        'signer_name',
        'signer_role',
        'signer_office_id',
        'signer_office_name',
        'signer_position',
        'signature_action',
        'signature_status',
        'consent_text',
        'signature_image_path',
        'typed_signature_name',
        'signatory_slot',
        'signatory_label',
        'password_confirmed_at',
        'signing_code_hash',
        'signing_code_sent_at',
        'signing_code_expires_at',
        'signing_code_verified_at',
        'signing_attempts',
        'document_hash_before',
        'signed_snapshot_hash',
        'signed_at',
        'declined_at',
        'revoked_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'password_confirmed_at' => 'datetime',
        'signing_code_sent_at' => 'datetime',
        'signing_code_expires_at' => 'datetime',
        'signing_code_verified_at' => 'datetime',
        'signing_attempts' => 'integer',
        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'signing_code_hash',
    ];

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    public function signatureRequest(): BelongsTo
    {
        return $this->belongsTo(SignatureRequest::class);
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(SignedDocumentSnapshot::class);
    }

    public function scopeForDocument(Builder $query, string $documentType, int|string $documentId): Builder
    {
        return $query->where('document_type', $documentType)
            ->where('document_id', $documentId);
    }

    public function isValid(): bool
    {
        return $this->signature_status === self::STATUS_SIGNED
            && $this->signed_at !== null
            && $this->revoked_at === null;
    }
}
