<?php

/**
 * Phase 15 — PaymentBundleResource direct-invocation authorization tests.
 *
 * MECHANISM: Same visible() → isHidden() → isDisabled() chain as ER/PR resources.
 * Unauthorized users who call mountTableAction are silently blocked.
 *
 * Overlap with existing tests:
 * - PaymentBundleFinanceUiTest: submit_for_signature (authorized), add_payment_request
 *   (authorized), create_bundle (authorized). No unauthorized counterparts. This file adds them.
 *
 * Net-new cases:
 * - finance_signer (no submit_for_signature perm) is blocked from submit_for_signature
 * - finance_executor (no update_draft perm) is blocked from add_payment_request
 * - finance_creator (no payment_bundles.mark_paid perm) is blocked from mark_paid bundle
 * - finance_executor (has payment_bundles.mark_paid) action IS mounted (permission passes),
 *   though service may reject for business reasons (signed PDF required)
 */

use App\Filament\Resources\PaymentBundleResource\Pages\ListPaymentBundles;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentRequest;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

// ─── helpers (p15bun_ prefix) ────────────────────────────────────────────────

function p15bun_draftBundle(): PaymentBundle
{
    $creator = FinanceCenterFlowFactory::financeCreator();

    return app(PaymentBundleService::class)->create($creator, ['title' => 'P15 Bundle Test']);
}

function p15bun_approvedPr(): PaymentRequest
{
    $creator = FinanceCenterFlowFactory::financeCreator();
    $coo = FinanceCenterFlowFactory::coo();
    $svc = app(PaymentRequestService::class);

    $pr = $svc->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT P15 Bundle Vendor',
        'bank_name' => 'Mandiri',
        'bank_account_number' => '9876543210',
        'amount' => 3000000,
        'currency' => 'IDR',
        'description' => 'P15 bundle PR',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, $coo);

    return $pr->fresh();
}

function p15bun_bundleWithPr(): PaymentBundle
{
    $creator = FinanceCenterFlowFactory::financeCreator();
    $bundle = p15bun_draftBundle();
    $pr = p15bun_approvedPr();
    app(PaymentBundleService::class)->addPaymentRequest($bundle, $pr, $creator);

    return $bundle->fresh();
}

// ─── submit_for_signature: authorized (finance_creator) ───────────────────────

it('finance_creator can submit_for_signature via direct mount (authorized)', function () {
    $bundle = p15bun_bundleWithPr();
    $creator = FinanceCenterFlowFactory::financeCreator();
    $fs1 = FinanceCenterFlowFactory::financeSigner();
    $fs2 = FinanceCenterFlowFactory::financeSigner();
    $exec = FinanceCenterFlowFactory::executiveSigner();

    Livewire::actingAs($creator)
        ->test(ListPaymentBundles::class)
        ->mountTableAction('submit_for_signature', $bundle)
        ->set('mountedTableActionsData.0.finance_signer_1', $fs1->id)
        ->set('mountedTableActionsData.0.finance_signer_2', $fs2->id)
        ->set('mountedTableActionsData.0.executive_signer', $exec->id)
        ->callMountedTableAction();

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
})->group('p15-bun');

// ─── submit_for_signature: unauthorized (finance_signer — no perm) ─────────────

it('finance_signer (no submit_for_signature perm) is blocked from submit_for_signature via direct mount', function () {
    $bundle = p15bun_bundleWithPr();
    $signer = FinanceCenterFlowFactory::financeSigner();
    $fs2 = FinanceCenterFlowFactory::financeSigner();
    $exec = FinanceCenterFlowFactory::executiveSigner();

    Livewire::actingAs($signer)
        ->test(ListPaymentBundles::class)
        ->mountTableAction('submit_for_signature', $bundle)
        ->set('mountedTableActionsData.0.finance_signer_1', $signer->id)
        ->set('mountedTableActionsData.0.finance_signer_2', $fs2->id)
        ->set('mountedTableActionsData.0.executive_signer', $exec->id)
        ->callMountedTableAction();

    // visible() = false → action is disabled → unmounts silently → status unchanged
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_DRAFT);
})->group('p15-bun');

// ─── add_payment_request: unauthorized (finance_executor — no update_draft perm) ──

it('finance_executor (no update_draft perm) is blocked from add_payment_request via direct mount', function () {
    $bundle = p15bun_draftBundle();
    $pr = p15bun_approvedPr();
    $executor = FinanceCenterFlowFactory::financeExecutor();

    Livewire::actingAs($executor)
        ->test(ListPaymentBundles::class)
        ->mountTableAction('add_payment_request', $bundle)
        ->set('mountedTableActionsData.0.payment_request_id', $pr->id)
        ->callMountedTableAction();

    // action blocked → no items added
    expect($bundle->fresh()->activeItems()->count())->toBe(0);
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);
})->group('p15-bun');

// ─── mark_paid (bundle): unauthorized (finance_creator — no payment_bundles.mark_paid) ──

it('finance_creator (no bundle mark_paid perm) is blocked from mark_paid bundle via direct mount', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    $bundle = PaymentBundle::factory()->create([
        'status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
        'created_by' => $creator->id,
    ]);
    $proof = UploadedFile::fake()->create('bundle_proof.pdf', 10, 'application/pdf');

    Livewire::actingAs($creator)
        ->test(ListPaymentBundles::class)
        ->mountTableAction('mark_paid', $bundle)
        ->set('mountedTableActionsData.0.proof', $proof)
        ->set('mountedTableActionsData.0.payment_reference', 'BUNDLE-UNAUTH-001')
        ->callMountedTableAction();

    // finance_creator lacks payment_bundles.mark_paid → action is hidden → blocked
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT)
        ->and($bundle->fresh()->paid_at)->toBeNull();
})->group('p15-bun');

// ─── mark_paid (bundle): authorized (finance_executor) — variant (a) ─────────
// The executor satisfies BOTH visible() conditions on mark_paid:
//   1. bundle status ∈ [authorized_for_payment, payment_recording, payment_proof_rejected]
//   2. financeCan('payment_bundles.mark_paid')
// We forceFill signed_pdf_attachment_id so the service precondition is met (same
// pattern as BatchPaymentBundleMarkPaidTest). Total amount is 0 so SoD is skipped.
// This is variant (a): full action invocation → bundle transitions to STATUS_PAID.

it('finance_executor (has mark_paid perm) can mark_paid bundle via direct mount (authorized)', function () {
    $executor = FinanceCenterFlowFactory::financeExecutor();
    $creator = FinanceCenterFlowFactory::financeCreator();
    $bundle = PaymentBundle::factory()->create([
        'status' => PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
        'created_by' => $creator->id,
    ]);
    // Satisfy the signed-PDF service precondition without generating a real PDF.
    $bundle->forceFill(['signed_pdf_attachment_id' => 999])->save();

    $proof = UploadedFile::fake()->create('bundle_proof.pdf', 10, 'application/pdf');

    Livewire::actingAs($executor)
        ->test(ListPaymentBundles::class)
        ->mountTableAction('mark_paid', $bundle)
        ->set('mountedTableActionsData.0.proof', $proof)
        ->set('mountedTableActionsData.0.payment_reference', 'BUNDLE-AUTH-001')
        ->callMountedTableAction();

    $fresh = $bundle->fresh();
    expect($fresh->status)->toBe(PaymentBundle::STATUS_PAID)
        ->and($fresh->paid_at)->not->toBeNull()
        ->and($fresh->payment_reference)->toBe('BUNDLE-AUTH-001');
})->group('p15-bun');
