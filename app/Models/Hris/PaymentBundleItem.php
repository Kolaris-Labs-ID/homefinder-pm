<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payable line inside a PaymentBundle. source_snapshot_json is the frozen plaintext of the
 * source (incl. full bank account) and is encrypted at rest; source_hash is a deterministic
 * sha256 over that plaintext so any later drift from the live source is detectable (Phase 10).
 */
class PaymentBundleItem extends Model
{
    use HasFactory;

    const SOURCE_PAYMENT_REQUEST = 'payment_request';

    const SOURCE_WEEKLY_ALLOWANCE_BATCH = 'weekly_allowance_batch';

    const STATUS_ACTIVE = 'active';

    const STATUS_RELEASED = 'released';

    const STATUS_PAYMENT_RECORDING = 'payment_recording';

    const STATUS_PAID = 'paid';

    const STATUS_FAILED = 'failed';

    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'payment_bundle_id', 'source_type', 'source_id',
        'payment_request_id', 'payment_request_line_id',
        'description', 'payee_name', 'payee_bank_name', 'payee_bank_account_last4',
        'amount', 'currency', 'source_snapshot_json', 'source_hash', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'source_snapshot_json' => 'encrypted:array',
        'status' => 'string',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(PaymentBundle::class, 'payment_bundle_id');
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class, 'payment_request_id');
    }
}
