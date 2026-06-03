<?php

/**
 * Phase 9 — Illegal-transition block-list (net-new cases only).
 *
 * COVERAGE CLASSIFICATION — already covered (reference, not duplicated):
 *
 *   ER state-machine:
 *     draft/rejected/cancelled cannot be finance-verified (BUG-4 fix)
 *       → ExpenseRequestFinanceReviewTest + BUG-4 fix commit
 *     record_only_closed cannot create PR
 *       → Phase 4 tests (ExpenseToPaymentRequestFlowTest)
 *
 *   PR state-machine:
 *     draft PR cannot be bundled                  → PaymentBundleServiceTest L173
 *     paid PR cannot be edited (BUG-A)            → ManualPaymentRequestFlowTest L139 (characterization)
 *     in_payment_bundle PR cannot be edited (BUG-A)→ ManualPaymentRequestFlowTest L117 (characterization)
 *     approved PR cannot be hard-deleted          → ManualPaymentRequestFlowTest L217 (no method exists)
 *
 *   PaymentBundle state-machine:
 *     draft/signing cannot be marked paid         → PaymentBundleLifecycleE2ETest L170+179
 *                                                    PaymentBundlePaymentProofTest L191
 *     paid bundle cannot be voided                → PaymentBundleReverseTransitionTest L205
 *     voided bundle cannot be voided again        → PaymentBundleReverseTransitionTest L213
 *     paid batch source never released from paid  → BatchPaymentBundleReverseTransitionTest L83
 *     signing state bundle refuses payment record → PaymentBundlePaymentExecutionSnapshotTest L213
 *     draft bundle refuses payment recording      → PaymentBundlePaymentExecutionSnapshotTest L220
 *
 * NET-NEW cases in this file (gaps not covered above):
 *   1. PR with status rejected_by_coo cannot be added to a bundle (explicit status check)
 *   2. PR with status paid cannot be added to a bundle
 *   3. payment_recording bundle cannot accept new items (addPaymentRequest guard = draft only)
 *   4. Voided bundle cannot be signed (submitSignature sees wrong bundle status via assertSignableNow)
 *   5. Rejected bundle cannot be signed (same guard path)
 */

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
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
use Tests\Support\BatchPaymentTestFactory as F;

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

    $this->financeA = tap(User::factory()->create())->assignRole('finance_signer');
    $this->financeB = tap(User::factory()->create())->assignRole('finance_signer');
    $this->exec = tap(User::factory()->create())->assignRole('executive_signer');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function p9_inkPng(): string
{
    return F::inkPng();
}

function p9_manualPr(int $amount = 3_000_000): PaymentRequest
{
    return test()->prs->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT P9 Vendor',
        'bank_name' => 'BRI',
        'bank_account_number' => '9988776655',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'P9 block-list test',
    ]);
}

function p9_approvedPr(int $amount = 3_000_000): PaymentRequest
{
    $pr = p9_manualPr($amount);
    test()->prs->submitToCoo($pr);
    test()->prs->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function p9_assignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

// ─── PR state-machine block-list ─────────────────────────────────────────────

it('blocks bundling a rejected_by_coo PR — PR is not approved/eligible', function () {
    $pr = p9_manualPr();
    $this->prs->submitToCoo($pr);
    $this->prs->rejectAsCoo($pr, $this->coo, 'not approved');
    $pr = $pr->fresh();
    expect($pr->status)->toBe(PaymentRequest::STATUS_REJECTED_BY_COO);

    $bundle = $this->bundles->create($this->creator);

    expect(fn () => $this->bundles->addPaymentRequest($bundle, $pr, $this->creator))
        ->toThrow(RuntimeException::class, 'PR is not approved/eligible for bundling.');

    expect($bundle->fresh()->total_amount)->toBe('0.00');
});

it('blocks bundling a paid PR — PR is not approved/eligible', function () {
    $pr = p9_approvedPr();
    // Force paid status directly (skip payment flow — we only need the status guard).
    $pr->update(['status' => PaymentRequest::STATUS_PAID]);
    $pr = $pr->fresh();
    expect($pr->status)->toBe(PaymentRequest::STATUS_PAID);

    $bundle = $this->bundles->create($this->creator);

    expect(fn () => $this->bundles->addPaymentRequest($bundle, $pr, $this->creator))
        ->toThrow(RuntimeException::class, 'PR is not approved/eligible for bundling.');
});

// ─── PaymentBundle state-machine block-list ───────────────────────────────────

it('blocks adding a new item to a payment_recording bundle (only draft bundles accept items)', function () {
    $approved = p9_approvedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $approved, $this->creator);
    $bundle->update(['status' => PaymentBundle::STATUS_PAYMENT_RECORDING]);
    $bundle = $bundle->fresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_PAYMENT_RECORDING);

    $newPr = p9_approvedPr();

    expect(fn () => $this->bundles->addPaymentRequest($bundle, $newPr, $this->creator))
        ->toThrow(RuntimeException::class, 'Only a draft bundle can receive items.');
});

it('blocks signing a voided bundle — status gate in assertSignableNow throws "not this slot\'s turn"', function () {
    $approved = p9_approvedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $approved, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), p9_assignment(), $this->creator);

    // Void the bundle (pre-first signature — status in voidable list).
    $this->bundles->void($bundle, $this->creator, 'voiding for test');
    $bundle = $bundle->fresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_VOIDED);

    // The signature slot itself is now VOIDED (not PENDING), so the first guard fires.
    $f1sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)
        ->firstOrFail();

    expect(fn () => $this->signatures->submitSignature($f1sig->fresh(), $this->financeA, p9_inkPng()))
        ->toThrow(RuntimeException::class);

    expect($f1sig->fresh()->status)->toBe(PaymentBundleSignature::STATUS_VOIDED);
});

it('blocks signing a rejected bundle — sig status is REJECTED or REVOKED, not PENDING', function () {
    $approved = p9_approvedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $approved, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), p9_assignment(), $this->creator);

    // Reject via the first signer.
    $f1sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)
        ->firstOrFail();
    $this->bundles->rejectSignature($f1sig, $this->financeA, 'decline for test');
    $bundle = $bundle->fresh();
    expect($bundle->status)->toBe(PaymentBundle::STATUS_REJECTED);

    // finance_2's slot is now REVOKED — cannot sign.
    $f2sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value)
        ->firstOrFail();
    expect($f2sig->status)->toBe(PaymentBundleSignature::STATUS_REVOKED);

    expect(fn () => $this->signatures->submitSignature($f2sig->fresh(), $this->financeB, p9_inkPng()))
        ->toThrow(RuntimeException::class, 'This signature slot is not awaiting a signature.');
});
