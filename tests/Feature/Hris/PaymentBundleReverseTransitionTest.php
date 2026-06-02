<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 13a — Reverse transitions: cancel (draft), void (pre-payment), rejectSignature (signing).
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
    $this->actor = User::factory()->create();

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');
});

function reverseInkPng(): string
{
    $img = imagecreatetruecolor(100, 50);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagealphablending($img, true);
    $black = imagecolorallocate($img, 0, 0, 0);
    imageline($img, 5, 25, 95, 25, $black);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

function reverseApprovedPr(int $amount = 5000000): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Office supplies',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function reverseValidAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function reverseSignSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): PaymentBundleSignature
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();

    return test()->signatures->submitSignature($sig, $sig->assignedUser, reverseInkPng());
}

function bundleInSigningState(): PaymentBundle
{
    $pr = reverseApprovedPr();
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);

    return test()->bundles->submitForSignature($bundle->fresh(), reverseValidAssignment(), test()->creator);
}

function authorizedBundle(): PaymentBundle
{
    $bundle = bundleInSigningState();

    reverseSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    reverseSignSlot($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    reverseSignSlot($bundle->fresh(), PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    return $bundle->fresh();
}

// ─── 1. cancel ───────────────────────────────────────────────────────────────────

it('cancels a draft bundle: status→cancelled, item→released, PR→approved_for_bundle', function () {
    $pr = reverseApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $bundle->fresh();

    $result = $this->bundles->cancel($bundle, $this->actor);

    expect($result->status)->toBe(PaymentBundle::STATUS_CANCELLED);

    $item = $bundle->items()->first();
    expect($item->fresh()->status)->toBe(PaymentBundleItem::STATUS_RELEASED);
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_FOR_BUNDLE);
});

it('emits payment_bundle.cancelled and payment_bundle.item_released audit events on cancel', function () {
    $pr = reverseApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->actor);

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.cancelled')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('throws when cancelling a non-draft bundle', function () {
    $bundle = $this->bundles->create($this->creator);
    $bundle->update(['status' => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1]);

    expect(fn () => $this->bundles->cancel($bundle->fresh(), $this->actor))
        ->toThrow(RuntimeException::class, 'Only a draft bundle can be cancelled.');
});

// ─── 2. void ─────────────────────────────────────────────────────────────────────

it('voids an authorized_for_payment bundle: status→voided, items released, sources eligible, all sigs voided', function () {
    $bundle = authorizedBundle();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);

    $result = $this->bundles->void($bundle, $this->actor, 'Test void reason');

    expect($result->status)->toBe(PaymentBundle::STATUS_VOIDED);
    expect($result->metadata['void_reason'])->toBe('Test void reason');
    expect($result->metadata['voided_by'])->toBe($this->actor->id);

    foreach ($bundle->items()->get() as $item) {
        expect($item->fresh()->status)->toBe(PaymentBundleItem::STATUS_RELEASED);
    }

    foreach ($bundle->signatures()->get() as $sig) {
        expect($sig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_VOIDED);
    }
});

it('void retains unsigned_pdf_attachment_id and signed_pdf_attachment_id (PDFs not deleted)', function () {
    $bundle = authorizedBundle();
    $unsignedId = $bundle->unsigned_pdf_attachment_id;
    $signedId = $bundle->signed_pdf_attachment_id;

    expect($unsignedId)->not->toBeNull();
    expect($signedId)->not->toBeNull();

    $result = $this->bundles->void($bundle, $this->actor, 'Retain PDFs check');

    expect($result->unsigned_pdf_attachment_id)->toBe($unsignedId);
    expect($result->signed_pdf_attachment_id)->toBe($signedId);
});

it('void emits payment_bundle.voided, signature_voided, and item_released audit events', function () {
    $bundle = authorizedBundle();

    $this->bundles->void($bundle, $this->actor, 'audit check');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.voided')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_voided')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('throws when voiding a paid bundle', function () {
    $bundle = $this->bundles->create($this->creator);
    $bundle->update(['status' => PaymentBundle::STATUS_PAID]);

    expect(fn () => $this->bundles->void($bundle->fresh(), $this->actor, 'reason'))
        ->toThrow(RuntimeException::class, 'This bundle cannot be voided.');
});

it('throws when voiding an already-voided bundle', function () {
    $bundle = $this->bundles->create($this->creator);
    $bundle->update(['status' => PaymentBundle::STATUS_VOIDED]);

    expect(fn () => $this->bundles->void($bundle->fresh(), $this->actor, 'reason'))
        ->toThrow(RuntimeException::class);
});

// ─── 3. rejectSignature ───────────────────────────────────────────────────────────

it('rejects at finance_1: bundle→rejected, rejecting sig→rejected with reason, other sigs→revoked, items released', function () {
    $pr = reverseApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), reverseValidAssignment(), $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);

    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();

    $result = $this->bundles->rejectSignature($f1sig, $this->financeA, 'Not authorized by me');

    expect($result->status)->toBe(PaymentBundle::STATUS_REJECTED);
    expect($result->metadata['rejection_reason'])->toBe('Not authorized by me');
    expect($result->metadata['rejected_by'])->toBe($this->financeA->id);

    $f1sig->refresh();
    expect($f1sig->status)->toBe(PaymentBundleSignature::STATUS_REJECTED);
    expect($f1sig->rejection_reason)->toBe('Not authorized by me');
    expect($f1sig->signed_by)->toBe($this->financeA->id);

    foreach ($bundle->signatures()->where('id', '!=', $f1sig->id)->get() as $other) {
        expect($other->fresh()->status)->toBe(PaymentBundleSignature::STATUS_REVOKED);
    }

    $item = $bundle->items()->first();
    expect($item->fresh()->status)->toBe(PaymentBundleItem::STATUS_RELEASED);
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_FOR_BUNDLE);
});

it('rejectSignature emits signature_rejected, signature_revoked, item_released, payment_bundle.rejected', function () {
    $bundle = bundleInSigningState();
    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();

    $this->bundles->rejectSignature($f1sig, $this->financeA, 'audit trail check');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_rejected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_revoked')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.rejected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('throws when a non-assigned user tries to rejectSignature', function () {
    $bundle = bundleInSigningState();
    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();
    $wrongUser = User::factory()->create();

    expect(fn () => $this->bundles->rejectSignature($f1sig, $wrongUser, 'attempt'))
        ->toThrow(RuntimeException::class, 'Only the assigned signer can reject this signature slot.');
});

it('throws when trying to reject a signature on a non-signing-state bundle', function () {
    $pr = reverseApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), reverseValidAssignment(), $this->creator);

    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->first();

    // Force bundle to authorized (non-signing state).
    $bundle->update(['status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT]);

    expect(fn () => $this->bundles->rejectSignature($f1sig->fresh(), $this->financeA, 'too late'))
        ->toThrow(RuntimeException::class);
});
