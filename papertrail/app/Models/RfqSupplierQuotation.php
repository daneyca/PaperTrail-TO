<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqSupplierQuotation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['link_token', 'token_hash', 'signature_data', 'supplier_tin', 'signer_ip_address', 'signer_user_agent'];

    protected $casts = [
        'link_token' => 'encrypted', 'issued_snapshot' => 'array', 'response_snapshot' => 'array',
        'expires_at' => 'datetime', 'viewed_at' => 'datetime', 'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime', 'total_amount' => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'viewed'], true) && $this->expires_at->isFuture();
    }

    public function responseIsIntact(): bool
    {
        return $this->response_snapshot && $this->response_hash && $this->signature_data
            && hash_equals($this->response_hash, self::hashSnapshot($this->response_snapshot))
            && hash_equals($this->response_snapshot['signature_hash'] ?? '', hash('sha256', $this->signature_data))
            && hash_equals($this->response_snapshot['issued_snapshot_hash'] ?? '', self::hashSnapshot($this->issued_snapshot));
    }

    public static function hashSnapshot(array $snapshot): string
    {
        // MySQL's JSON storage can reorder object keys. Hash a canonical representation.
        $normalize = function ($value) use (&$normalize) {
            if (! is_array($value)) {
                return $value;
            }
            if (! array_is_list($value)) {
                ksort($value);
            }
            return array_map($normalize, $value);
        };
        return hash('sha256', json_encode($normalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
