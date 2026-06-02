<?php

namespace App\Services\Hris;

use App\Models\Hris\PaymentBundleSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Signing-link token gate (Phase 6). Two independent layers protect the link:
 *   1. URL::signedRoute HMAC (registered on the route) makes the URL tamper-proof but is non-expiring.
 *   2. The DB token state below (hashed token + expiry + one-time-use + revocation, bound per signature)
 *      is the authoritative expiry/consumption gate.
 * The signed URL is intentionally NON-expiring so it survives the login round-trip; token_expires_at owns expiry.
 */
class PaymentBundleSignatureService
{
    public function __construct(protected FinanceAuditLogger $audit) {}

    public function createSigningLink(PaymentBundleSignature $sig, User $signer, int $ttlHours = 72): string
    {
        $rawToken = Str::random(64);

        DB::transaction(function () use ($sig, $signer, $rawToken, $ttlHours) {
            $sig->forceFill([
                'assigned_user_id' => $signer->id,
                'required_permission' => $sig->signature_slot->requiredPermission(),
                'signature_token_hash' => hash('sha256', $rawToken),
                'token_expires_at' => now()->addHours($ttlHours),
                'token_used_at' => null,
                'token_revoked_at' => null,
                'status' => PaymentBundleSignature::STATUS_PENDING,
            ])->save();

            $this->audit->log($sig, 'payment_bundle.signature_link_created', $signer, ['after' => [
                'signature_id' => $sig->id,
                'slot' => $sig->signature_slot->value,
                'assigned_user_id' => $sig->assigned_user_id,
                'token_expires_at' => $sig->token_expires_at?->toIso8601String(),
            ]]);
        });

        return URL::signedRoute('sign.payment-bundle', [
            'bundle' => $sig->payment_bundle_id,
            'paymentSignature' => $sig->id,
            'token' => $rawToken,
        ]);
    }

    /** @return 'valid'|'mismatch'|'expired'|'used'|'revoked'|'missing' */
    public function tokenStatus(PaymentBundleSignature $sig, string $token): string
    {
        if ($sig->signature_token_hash === null) {
            return 'missing';
        }
        if ($sig->token_revoked_at !== null) {
            return 'revoked';
        }
        if ($sig->token_used_at !== null) {
            return 'used';
        }
        if ($sig->token_expires_at !== null && $sig->token_expires_at->isPast()) {
            return 'expired';
        }
        if (! hash_equals($sig->signature_token_hash, hash('sha256', $token))) {
            return 'mismatch';
        }

        return 'valid';
    }

    /** Preview-safe: validates without mutating. */
    public function assertTokenValid(PaymentBundleSignature $sig, string $token): void
    {
        $status = $this->tokenStatus($sig, $token);

        if ($status !== 'valid') {
            throw new RuntimeException("Invalid signing token: {$status}");
        }
    }

    /** One-time-use; called by Phase 7 after a successful signature. Idempotent: never re-stamps. */
    public function consumeToken(PaymentBundleSignature $sig): void
    {
        if ($sig->token_used_at !== null) {
            return;
        }

        $sig->forceFill(['token_used_at' => now()])->save();
    }

    public function revokeToken(PaymentBundleSignature $sig): void
    {
        $sig->forceFill([
            'token_revoked_at' => now(),
            'status' => PaymentBundleSignature::STATUS_REVOKED,
        ])->save();
    }
}
