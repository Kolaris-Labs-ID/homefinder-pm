<?php

namespace App\Services\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentBundle;
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
    public function __construct(
        protected FinanceAuditLogger $audit,
        protected FinanceAttachmentService $attachments,
        protected PaymentBundleDocumentService $documents,
        protected BatchPaymentBundleService $batchSources,
    ) {}

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

    /**
     * Capture and persist a drawn signature for one slot, workflow-aware (Phase 7 capture + Phase 8
     * orchestration). Locks BOTH the bundle and signature rows, enforces sequential ordering +
     * segregation-of-duties via assertSignableNow() BEFORE capture, captures via the Phase 7
     * primitive, then recomputes the bundle status via advanceAfterSignature() — all in one
     * transaction. The bundle-row lock + status gating make the final authorization fire exactly
     * once and make a double-click safe (the second attempt sees an advanced status / used token).
     */
    public function submitSignature(PaymentBundleSignature $sig, User $signer, string $imageDataUri, array $context = []): PaymentBundleSignature
    {
        return DB::transaction(function () use ($sig, $signer, $imageDataUri, $context) {
            // Lock the bundle first (parent before child) so the whole turn is serialized.
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($sig->payment_bundle_id);
            $sig = PaymentBundleSignature::lockForUpdate()->findOrFail($sig->id);

            if ($sig->status !== PaymentBundleSignature::STATUS_PENDING) {
                throw new RuntimeException('This signature slot is not awaiting a signature.');
            }

            $this->assertSignableNow($bundle, $sig, $signer);
            $this->batchSources->assertNoSourceDrift($bundle, $signer);

            $pngBytes = $this->decodePngDataUri($imageDataUri);

            if (! $this->pngHasInk($pngBytes)) {
                throw new RuntimeException('The signature cannot be empty.');
            }

            $signedAt = now();

            $attachment = $this->attachments->storeGeneratedFile(
                $bundle,
                $pngBytes,
                FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE,
                "signature-{$sig->signature_slot->value}-{$sig->id}.png",
                'image/png',
                $signer,
            );

            $hash = hash('sha256',
                $signer->id
                .$sig->signature_slot->value
                .$bundle->canonical_payload_hash
                .$signedAt->toIso8601String()
                .$attachment->file_hash
            );

            $sig->forceFill([
                'status' => PaymentBundleSignature::STATUS_SIGNED,
                'signed_by' => $signer->id,
                'signed_at' => $signedAt,
                'signature_image_attachment_id' => $attachment->id,
                'signature_name' => $signer->name,
                'signature_role' => $sig->signature_slot->label(),
                'signature_ip' => $context['ip'] ?? null,
                'signature_user_agent' => $context['user_agent'] ?? null,
                'signature_hash' => $hash,
            ])->save();

            $this->consumeToken($sig);

            $this->audit->log($sig, 'payment_bundle.signature_submitted', $signer, [
                'after' => [
                    'signature_id' => $sig->id,
                    'slot' => $sig->signature_slot->value,
                    'signature_hash' => $hash,
                    'signature_image_attachment_id' => $attachment->id,
                ],
            ]);

            $this->advanceAfterSignature($bundle);

            $bundle->refresh();
            if ($bundle->status === PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT
                && $bundle->signed_pdf_attachment_id === null) {
                $this->documents->generateSignedPdf($bundle, $signer);
            }

            return $sig->fresh();
        });
    }

    /**
     * Sequential + segregation-of-duties gate for a single signing attempt. Throws unless:
     *   - the bundle status is exactly the one mapped to this slot's turn (status-driven ordering),
     *   - the slot being signed is pending,
     *   - the signer is not already the signed_by of another SIGNED signature on this bundle
     *     (sign-time SoD / defense in depth against tampered assignments),
     *   - the signer is the slot's assigned_user_id (or, defensively when no signer is assigned,
     *     holds the slot's required_permission).
     */
    public function assertSignableNow(PaymentBundle $bundle, PaymentBundleSignature $sig, User $signer): void
    {
        if ($sig->status !== PaymentBundleSignature::STATUS_PENDING) {
            throw new RuntimeException('This signature slot is not awaiting a signature.');
        }

        if ($bundle->status !== $this->expectedBundleStatusFor($sig->signature_slot)) {
            throw new RuntimeException("It is not this slot's turn to sign.");
        }

        $alreadySigned = $bundle->signatures()
            ->where('status', PaymentBundleSignature::STATUS_SIGNED)
            ->where('signed_by', $signer->id)
            ->where('id', '!=', $sig->id)
            ->exists();

        if ($alreadySigned) {
            throw new RuntimeException('This user has already signed another slot on this bundle.');
        }

        if ($sig->assigned_user_id !== null) {
            if ($signer->id !== $sig->assigned_user_id) {
                throw new RuntimeException('You are not the assigned signer for this slot.');
            }
        } elseif (! $signer->can($sig->required_permission)) {
            throw new RuntimeException('You do not have permission to sign this slot.');
        }
    }

    /**
     * Recompute the bundle status from its signatures after a successful capture. Must run inside
     * the locked transaction. Advances finance_1 → finance_2 → executive, and on the executive
     * signature transitions to authorized_for_payment EXACTLY once (idempotent: a no-op once already
     * authorized). Phase 9 will hook signed-PDF generation onto this authorization.
     */
    public function advanceAfterSignature(PaymentBundle $bundle): void
    {
        if ($bundle->status === PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT) {
            return;
        }

        $signed = fn (PaymentBundleSignatureSlot $slot): bool => $bundle->signatures()
            ->where('signature_slot', $slot->value)
            ->where('status', PaymentBundleSignature::STATUS_SIGNED)
            ->exists();

        if ($bundle->status === PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1
            && $signed(PaymentBundleSignatureSlot::FINANCE_SIGNER_1)) {
            $bundle->update(['status' => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2]);

            return;
        }

        if ($bundle->status === PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2
            && $signed(PaymentBundleSignatureSlot::FINANCE_SIGNER_2)) {
            $bundle->update(['status' => PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE]);

            return;
        }

        if ($bundle->status === PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE
            && $signed(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER)) {
            $oldStatus = $bundle->status;
            $bundle->update([
                'status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
                'authorized_at' => now(),
            ]);

            $this->audit->log($bundle, 'payment_bundle.authorized', null, [
                'before' => ['status' => $oldStatus],
                'after' => [
                    'status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
                    'authorized_at' => $bundle->authorized_at?->toIso8601String(),
                    'canonical_payload_hash' => $bundle->canonical_payload_hash,
                ],
            ]);

            $this->batchSources->markBundleAuthorized($bundle->fresh());
        }
    }

    private function expectedBundleStatusFor(PaymentBundleSignatureSlot $slot): string
    {
        return match ($slot) {
            PaymentBundleSignatureSlot::FINANCE_SIGNER_1 => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
            PaymentBundleSignatureSlot::FINANCE_SIGNER_2 => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2,
            PaymentBundleSignatureSlot::EXECUTIVE_SIGNER => PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE,
        };
    }

    /** Decode a PNG data URI; throws RuntimeException on invalid input. */
    private function decodePngDataUri(string $dataUri): string
    {
        if (! str_starts_with($dataUri, 'data:image/png;base64,')) {
            throw new RuntimeException('A signature image is required.');
        }

        $b64 = substr($dataUri, strlen('data:image/png;base64,'));
        $bytes = base64_decode($b64, strict: true);

        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('A signature image is required.');
        }

        return $bytes;
    }

    /** Returns true if the PNG contains at least one non-transparent, non-white pixel. */
    private function pngHasInk(string $bytes): bool
    {
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return false;
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);

        $w = imagesx($img);
        $h = imagesy($img);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $color = imagecolorat($img, $x, $y);
                $a = ($color >> 24) & 0x7F;
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;

                if ($a < 64 && ! ($r > 240 && $g > 240 && $b > 240)) {
                    imagedestroy($img);

                    return true;
                }
            }
        }

        imagedestroy($img);

        return false;
    }
}
