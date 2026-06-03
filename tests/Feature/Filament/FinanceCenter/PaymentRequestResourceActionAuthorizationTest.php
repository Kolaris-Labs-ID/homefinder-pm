<?php

/**
 * Phase 15 — PaymentRequestResource direct-invocation authorization tests.
 *
 * MECHANISM: Same as ER resource — isDisabled() → isHidden() → visible() closure.
 * An unauthorized user who directly calls mountTableAction is silently blocked
 * because the action is hidden (visible=false) → disabled → unmounted on mount.
 *
 * Overlap with existing tests:
 * - FinanceFilamentActionTest: mark_paid (authorized success), void (authorized success),
 *   approve on PaymentApprovalView (authorized success). No unauthorized counterparts there.
 *   This file adds unauthorized direct-invocation cases as net-new coverage.
 * - FinanceFilamentAccessTest: canViewAny() static checks. No Livewire invocation.
 *
 * Net-new cases:
 * - finance_creator (no mark_paid perm) cannot mark_paid via direct mount
 * - finance_executor (no void perm) cannot void via direct mount
 * - PaymentApprovalView: COO can access; finance_creator cannot (abort_unless 403)
 * - COO approve action on PaymentApprovalView (authorized baseline)
 */

use App\Filament\Pages\PaymentApprovalView;
use App\Filament\Resources\PaymentRequestResource;
use App\Filament\Resources\PaymentRequestResource\Pages\ListPaymentRequests;
use App\Models\Hris\PaymentRequest;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('private');
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

// ─── helpers (p15pr_ prefix) ────────────────────────────────────────────────

function p15pr_draftPr(): PaymentRequest
{
    $creator = FinanceCenterFlowFactory::financeCreator();

    return app(PaymentRequestService::class)->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT P15 Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => 2500000,
        'currency' => 'IDR',
        'description' => 'P15 test PR',
    ]);
}

function p15pr_approvedPr(): PaymentRequest
{
    $pr = p15pr_draftPr();
    $svc = app(PaymentRequestService::class);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, FinanceCenterFlowFactory::coo());

    return $pr->fresh();
}

// ─── mark_paid: authorized (finance_executor) ─────────────────────────────────

it('finance_executor can mark_paid via direct mount (authorized)', function () {
    $pr = p15pr_approvedPr();
    $executor = FinanceCenterFlowFactory::financeExecutor();
    $proof = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');

    Livewire::actingAs($executor)
        ->test(ListPaymentRequests::class)
        ->mountTableAction('mark_paid', $pr)
        ->set('mountedTableActionsData.0.payment_reference', 'TRX-P15-001')
        ->set('mountedTableActionsData.0.proof', $proof)
        ->callMountedTableAction();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
})->group('p15-pr');

// ─── mark_paid: unauthorized (finance_reviewer — no payment_requests.view_all) ─

it('finance_reviewer cannot access PaymentRequestResource (no view_all perm)', function () {
    $reviewer = FinanceCenterFlowFactory::financeReviewer();
    actingAs($reviewer);

    expect(PaymentRequestResource::canViewAny())->toBeFalse();
})->group('p15-pr');

// ─── mark_paid: unauthorized (finance_creator — has view_all, no mark_paid perm) ──

it('finance_creator (no mark_paid perm) is blocked from mark_paid via direct mount', function () {
    $pr = p15pr_approvedPr();
    $creator = FinanceCenterFlowFactory::financeCreator();
    $proof = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->mountTableAction('mark_paid', $pr)
        ->set('mountedTableActionsData.0.payment_reference', 'TRX-P15-UNAUTH')
        ->set('mountedTableActionsData.0.proof', $proof)
        ->callMountedTableAction();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO)
        ->and($pr->fresh()->paid_by)->toBeNull();
})->group('p15-pr');

// ─── void: authorized (finance_creator) ───────────────────────────────────────

it('finance_creator can void via direct mount (authorized)', function () {
    $pr = p15pr_draftPr();
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(ListPaymentRequests::class)
        ->mountTableAction('void', $pr)
        ->set('mountedTableActionsData.0.reason', 'Cancelled per request')
        ->callMountedTableAction();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_VOIDED);
})->group('p15-pr');

// ─── void: unauthorized (finance_executor — no void perm) ─────────────────────

it('finance_executor (no void perm) is blocked from void via direct mount', function () {
    $pr = p15pr_draftPr();
    $executor = FinanceCenterFlowFactory::financeExecutor();

    Livewire::actingAs($executor)
        ->test(ListPaymentRequests::class)
        ->mountTableAction('void', $pr)
        ->set('mountedTableActionsData.0.reason', 'Unauthorized void attempt')
        ->callMountedTableAction();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_DRAFT)
        ->and($pr->fresh()->voided_at)->toBeNull();
})->group('p15-pr');

// ─── PaymentApprovalView: static canAccess enforcement ────────────────────────

it('COO can access PaymentApprovalView (authorized)', function () {
    $coo = FinanceCenterFlowFactory::coo();
    actingAs($coo);

    expect(PaymentApprovalView::canAccess())->toBeTrue();
})->group('p15-pr');

it('finance_creator cannot access PaymentApprovalView (page-level block)', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    actingAs($creator);

    expect(PaymentApprovalView::canAccess())->toBeFalse();
})->group('p15-pr');

it('finance_creator Livewire mount of PaymentApprovalView is aborted (403)', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();

    // The mountCanAuthorizeAccess lifecycle hook calls abort_unless(canAccess(), 403).
    // Livewire wraps this in an HTTP response — test the component responds with error.
    Livewire::actingAs($creator)
        ->test(PaymentApprovalView::class)
        ->assertStatus(403);
})->group('p15-pr');

// ─── PaymentApprovalView: COO approve action (authorized baseline) ─────────────

it('COO can call approve action on PaymentApprovalView (authorized)', function () {
    $pr = p15pr_draftPr();
    app(PaymentRequestService::class)->submitToCoo($pr);
    $pr = $pr->fresh();

    $coo = FinanceCenterFlowFactory::coo();

    Livewire::actingAs($coo)
        ->test(PaymentApprovalView::class)
        ->callTableAction('approve', $pr)
        ->assertHasNoTableActionErrors();

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);
})->group('p15-pr');
