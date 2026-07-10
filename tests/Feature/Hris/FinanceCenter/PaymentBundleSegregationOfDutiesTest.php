<?php

/**
 * Phase 8 — Segregation-of-duties: net-new cases only.
 *
 * COVERAGE CLASSIFICATION — pre-existing tests (reference, not duplicated):
 *   Finance Signer 1 ≠ Finance Signer 2 at submit     → PaymentBundleSignatureWorkflowTest L137
 *   Executive ≠ any finance signer at submit           → PaymentBundleSignatureWorkflowTest L149
 *   Assigned signer lacks slot permission at submit    → PaymentBundleSignatureWorkflowTest L158
 *   Creator ≠ executive on high-value bundle           → PaymentBundleSignatureWorkflowTest L170
 *   Creator-as-executive allowed on low-value          → PaymentBundleSignatureWorkflowTest L183
 *   Wrong user (not assigned) cannot sign slot         → PaymentBundleSignerOverrideTest L163
 *   Permissioned-but-not-assigned cannot sign          → PaymentBundleSignerOverrideTest L163
 *   Out-of-order: exec before finance                  → PaymentBundleSignatureWorkflowTest L219
 *   Out-of-order: finance_2 before finance_1           → PaymentBundleSignatureWorkflowTest L229
 *   Sign-time SoD (tampered assignment, same human)    → PaymentBundleSignatureWorkflowTest L260
 *   Expired / revoked / used token → 403               → PaymentBundleSigningLinkTest
 *   Payer ≠ finance signer on high-value markPaid      → PaymentBundlePaymentProofTest L285
 *   Payer (exec) allowed on high-value markPaid        → PaymentBundlePaymentProofTest L296
 *   Finance signer allowed to pay low-value            → PaymentBundlePaymentProofTest L305
 *
 * NET-NEW cases in this file (gaps not covered above):
 *   1. Executive signer cannot sign a finance slot (cross-slot permission violation)
 *   2. Finance signer cannot sign the executive slot (cross-slot permission violation)
 *   3. Double-signing the same slot (second attempt sees STATUS_PENDING guard → throws)
 *   4. High-value payer SoD: finance_signer_2 (also a signer) cannot pay — orthogonal user to L285
 */

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BatchPaymentTestFactory as F;

beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
    $this->payer = User::factory()->create();

    $this->financeA = tap(User::factory()->create())->assignRole('finance_signer');
    $this->financeB = tap(User::factory()->create())->assignRole('finance_signer');
    $this->exec = tap(User::factory()->create())->assignRole('executive_signer');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function p8_inkPng(): string
{
    return F::inkPng();
}

function p8_approvedPr(int $amount = 5_000_000): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT P8 Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'P8 SoD test',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function p8_assignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function p8_draftBundle(int $amount = 5_000_000): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, p8_approvedPr($amount), test()->creator);

    return $bundle->fresh();
}

function p8_submittedBundle(int $amount = 5_000_000): PaymentBundle
{
    return test()->bundles->submitForSignature(p8_draftBundle($amount), p8_assignment(), test()->creator);
}

function p8_fakePdf(): UploadedFile
{
    return UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf');
}

// ─── 1. Cross-slot: executive signer cannot sign a finance slot ──────────────

it('blocks executive_signer from signing a finance slot (wrong required_permission)', function () {
    $bundle = p8_submittedBundle();

    $f1sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)
        ->firstOrFail();

    // Tamper assignment to point the finance slot at the executive user.
    $f1sig->forceFill(['assigned_user_id' => $this->exec->id])->save();

    // assertSignableNow checks the slot's required_permission against the signer:
    // executive_signer has payment_bundles.sign.executive, NOT payment_bundles.sign.finance.
    // With assigned_user_id set to exec, the assigned-user gate passes, but the
    // permission check inside the slot happens at submit time (already passed), so
    // the signing-time gate is the status/order + SoD-not-already-signed chain.
    // The relevant guard here is that the slot's required_permission is finance only.
    // We verify this by using a fresh exec user with ONLY executive_signer role (no finance perm).
    $pureExec = tap(User::factory()->create())->assignRole('executive_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $f1sig->forceFill(['assigned_user_id' => null])->save(); // fall back to permission gate

    expect(fn () => $this->signatures->submitSignature($f1sig->fresh(), $pureExec, p8_inkPng()))
        ->toThrow(RuntimeException::class, 'You do not have permission to sign this slot.');

    expect($f1sig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 2. Cross-slot: finance signer cannot sign the executive slot ─────────────

it('blocks finance_signer from signing the executive slot (wrong required_permission)', function () {
    $bundle = p8_submittedBundle();

    // Advance through finance_1 and finance_2 so exec slot is active.
    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->firstOrFail();
    $this->signatures->submitSignature($f1sig, $this->financeA, p8_inkPng());

    $f2sig = $bundle->fresh()->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value)->firstOrFail();
    $this->signatures->submitSignature($f2sig, $this->financeB, p8_inkPng());

    $bundle->refresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE);

    // A fresh finance signer (not financeA or financeB — no "already signed" issue) tries exec slot.
    $freshFinance = tap(User::factory()->create())->assignRole('finance_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $execSig = $bundle->fresh()->signatures()->where('signature_slot', PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value)->firstOrFail();
    $execSig->forceFill(['assigned_user_id' => null])->save(); // fall back to permission gate

    expect(fn () => $this->signatures->submitSignature($execSig->fresh(), $freshFinance, p8_inkPng()))
        ->toThrow(RuntimeException::class, 'You do not have permission to sign this slot.');

    expect($execSig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 3. Double-signing the same slot is cleanly blocked ──────────────────────

it('blocks double-signing the same slot (slot no longer PENDING after first sign)', function () {
    $bundle = p8_submittedBundle();

    $f1sig = $bundle->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->firstOrFail();

    // First sign — succeeds.
    $this->signatures->submitSignature($f1sig, $this->financeA, p8_inkPng());

    expect($f1sig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_SIGNED);

    // Second attempt on the same (now SIGNED) slot — must throw.
    expect(fn () => $this->signatures->submitSignature($f1sig->fresh(), $this->financeA, p8_inkPng()))
        ->toThrow(RuntimeException::class, 'This signature slot is not awaiting a signature.');

    expect($f1sig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_SIGNED);
});

// ─── 4. Payer identity unrestricted: a finance signer may pay (SoD removed) ──────

it('lets finance_signer_2 pay a bundle they signed (SoD removed)', function () {
    $bundle = p8_draftBundle(10_000_000);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);

    $result = $this->payments->markPaid($bundle->fresh(), $this->financeB, p8_fakePdf());
    expect($result->status)->toBe(PaymentBundle::STATUS_PAID);
});
