<?php

namespace App\Models\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single signature slot on a PaymentBundle (finance_signer_1/2, executive_signer). Holds the
 * signing-link token (hashed), the captured signature, and the per-signature audit fields.
 */
class PaymentBundleSignature extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_SIGNED = 'signed';

    const STATUS_REJECTED = 'rejected';

    const STATUS_EXPIRED = 'expired';

    const STATUS_REVOKED = 'revoked';

    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'payment_bundle_id', 'signature_slot', 'required_permission', 'assigned_user_id',
        'signature_token_hash', 'token_expires_at', 'token_used_at', 'token_revoked_at',
        'signed_by', 'signed_at', 'signature_image_attachment_id',
        'signature_name', 'signature_role', 'signature_ip', 'signature_user_agent', 'signature_hash',
        'status', 'rejection_reason',
    ];

    protected $casts = [
        'signature_slot' => PaymentBundleSignatureSlot::class,
        'token_expires_at' => 'datetime',
        'token_used_at' => 'datetime',
        'token_revoked_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(PaymentBundle::class, 'payment_bundle_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function signatureImage(): BelongsTo
    {
        return $this->belongsTo(FinanceAttachment::class, 'signature_image_attachment_id');
    }
}
