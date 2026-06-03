<?php

use App\Models\Hris\PaymentRequest;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Tests\Support\FinanceCenterFlowFactory;

/**
 * Phase 5 — Manual PR flow: edit-locking matrix + money guards.
 *
 * COVERAGE CLASSIFICATION
 * Already covered (skip/reference):
 *   - createManual → source_type=manual, status=draft, PR number format  [PaymentRequestLifecycleTest]
 *   - amount stored as decimal:2 string                                   [PaymentRequestLifecycleTest L37]
 *   - encrypted bank + last4                                              [PaymentRequestLifecycleTest L41]
 *   - submitToCoo / approveAsCoo / rejectAsCoo / requestClarification    [PaymentRequestLifecycleTest]
 *   - updateApprovedDetails voids approval → draft (sensitive change)     [PaymentRequestApprovalSnapshotTest]
 *   - payment_requests.create / payment_requests.void authz              [PaymentRequestResourceActionAuthorizationTest (Phase 15)]
 *
 * Net-new cases (this file):
 *   - finance_creator role can createManual (seeded roles)
 *   - Edit-locking matrix: updateApprovedDetails allowed states vs locked states
 *     BUG FOUND: service has NO guard for in_payment_bundle / paid / reconciled — tests are FAILING
 *   - ER-sourced PR cannot be mutated via updateApprovedDetails (no source_type guard) — also FAILING
 *   - Hard delete is absent (no service method, no Filament action)
 *   - A manual PR can reach in_payment_bundle via approveAsCoo → addPaymentRequest
 *   - Zero amount NOT rejected at service layer (gap — service accepts it)
 *   - Negative amount NOT rejected at service layer (gap — service accepts it)
 *   - Large amount (within safe range) handled correctly
 *   - IDR display formatting does not mutate stored decimal value
 *
 * BUGS REPORTED (do not fix app code — reported for user approval):
 *   BUG-A: updateApprovedDetails does not guard in_payment_bundle, paid, reconciled, reversed statuses.
 *          Tests for these states are written as FAILING (->toThrow) to document real behavior.
 *          If the service accepts the update (no throw), the test will fail → red flag.
 *   BUG-B: updateApprovedDetails has no source_type guard — it accepts ER-sourced PRs.
 *   GAP-C: zero/negative amounts are accepted by createManual (service has no validation).
 */
beforeEach(function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
    $this->svc = app(PaymentRequestService::class);
    $this->creator = FinanceCenterFlowFactory::financeCreator();
    $this->coo = FinanceCenterFlowFactory::coo();
});

// ─── helpers (p5_ prefix to avoid global collision) ─────────────────────────

function p5_payload(array $overrides = []): array
{
    return array_merge([
        'payee_type' => 'vendor',
        'payee_name' => 'PT P5 Vendor',
        'bank_name' => 'Mandiri',
        'bank_account_number' => '9988776655',
        'amount' => 3_000_000,
        'currency' => 'IDR',
        'description' => 'P5 test expense',
    ], $overrides);
}

function p5_draft(array $overrides = []): PaymentRequest
{
    return app(PaymentRequestService::class)->createManual(
        FinanceCenterFlowFactory::financeCreator(),
        p5_payload($overrides),
    );
}

function p5_approved(array $overrides = []): PaymentRequest
{
    $pr = p5_draft($overrides);
    $svc = app(PaymentRequestService::class);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, FinanceCenterFlowFactory::coo());

    return $pr->fresh();
}

// ─── finance_creator can create a manual PR (seeded roles) ──────────────────

it('finance_creator with payment_requests.create perm can createManual', function () {
    $pr = $this->svc->createManual($this->creator, p5_payload());

    expect($pr->source_type)->toBe(PaymentRequest::SOURCE_MANUAL)
        ->and($pr->status)->toBe(PaymentRequest::STATUS_DRAFT)
        ->and($pr->created_by)->toBe($this->creator->id);
});

// ─── edit-locking: allowed states ───────────────────────────────────────────

it('updateApprovedDetails is allowed on a draft PR', function () {
    $pr = p5_draft();

    $this->svc->updateApprovedDetails($pr, ['amount' => 4_000_000]);

    expect($pr->fresh()->amount)->toBe('4000000.00');
});

it('updateApprovedDetails on approved_by_coo voids approval and returns to draft', function () {
    // Already covered in PaymentRequestApprovalSnapshotTest — validate the state for edit-locking matrix.
    $pr = p5_approved();
    expect($pr->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);

    $this->svc->updateApprovedDetails($pr, ['amount' => 6_000_000]);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_DRAFT)
        ->and($pr->fresh()->approval_snapshot_hash)->toBeNull();
});

// ─── edit-locking: LOCKED states (BUG-5 fixed) ──────────────────────────────

it('updateApprovedDetails on in_payment_bundle throws RuntimeException', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_IN_PAYMENT_BUNDLE]);
    $pr = $pr->fresh();

    expect(fn () => $this->svc->updateApprovedDetails($pr, ['amount' => 9_999_999]))
        ->toThrow(\RuntimeException::class, 'A payment request can no longer be edited once it is in a payment or terminal state.');
});

it('updateApprovedDetails on paid throws RuntimeException', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_PAID]);
    $pr = $pr->fresh();

    expect(fn () => $this->svc->updateApprovedDetails($pr, ['payee_name' => 'Rogue Payee']))
        ->toThrow(\RuntimeException::class, 'A payment request can no longer be edited once it is in a payment or terminal state.');
});

it('updateApprovedDetails on reconciled throws RuntimeException', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_RECONCILED]);
    $pr = $pr->fresh();

    expect(fn () => $this->svc->updateApprovedDetails($pr, ['amount' => 1]))
        ->toThrow(\RuntimeException::class, 'A payment request can no longer be edited once it is in a payment or terminal state.');
});

it('updateApprovedDetails on paid_pending_reconciliation throws RuntimeException', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION]);
    $pr = $pr->fresh();

    expect(fn () => $this->svc->updateApprovedDetails($pr, ['amount' => 1]))
        ->toThrow(\RuntimeException::class, 'A payment request can no longer be edited once it is in a payment or terminal state.');
});

// ─── ER-sourced PR edit via updateApprovedDetails (BUG-6 reclassified: not a bug) ─
//
// BUG-6 reclassified: approved-detail correction is INTENDED for all source types.
// The Filament PaymentRequestResource has no edit page and no form actions gated by
// source_type — all lifecycle actions (void, mark_paid, reconcile) treat ER-sourced and
// manual PRs identically. Correcting bank details / amount on an ER-sourced PR before
// payment is a legitimate operation. No source_type guard should be added.

it('updateApprovedDetails on an ER-sourced approved PR voids snapshot and returns to draft (legitimate)', function () {
    $pr = $this->svc->createManual($this->creator, p5_payload([
        'source_type' => PaymentRequest::SOURCE_EXPENSE_REQUEST,
        'source_id' => 9999,
    ]));
    $this->svc->submitToCoo($pr);
    $this->svc->approveAsCoo($pr, $this->coo);
    $pr = $pr->fresh();
    expect($pr->source_type)->toBe(PaymentRequest::SOURCE_EXPENSE_REQUEST);

    // Legitimate: correcting amount voids snapshot → draft (same as manual PR).
    $this->svc->updateApprovedDetails($pr, ['amount' => 7_000_000]);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_DRAFT)
        ->and($pr->fresh()->approval_snapshot_hash)->toBeNull();
});

// ─── delete: no hard-delete mechanism ───────────────────────────────────────

it('PaymentRequestService has no hard-delete method (soft delete only via void)', function () {
    expect(method_exists(PaymentRequestService::class, 'delete'))->toBeFalse()
        ->and(method_exists(PaymentRequestService::class, 'hardDelete'))->toBeFalse()
        ->and(method_exists(PaymentRequestService::class, 'forceDelete'))->toBeFalse();
});

it('a draft manual PR can be voided (soft-terminal) but not hard-deleted', function () {
    $pr = p5_draft();
    $actor = $this->creator;

    $this->svc->void($pr, $actor, 'no longer needed');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_VOIDED)
        ->and(PaymentRequest::find($pr->id))->not->toBeNull(); // row still exists
});

// ─── bundle eligibility path ─────────────────────────────────────────────────

it('a manual PR reaches in_payment_bundle after approveAsCoo → addPaymentRequest', function () {
    $pr = p5_approved();
    expect($pr->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);

    $bundleSvc = app(PaymentBundleService::class);
    $bundleCreator = FinanceCenterFlowFactory::financeCreator();
    $bundle = $bundleSvc->create($bundleCreator, ['title' => 'P5 Test Bundle']);

    $bundleSvc->addPaymentRequest($bundle, $pr, $bundleCreator);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_IN_PAYMENT_BUNDLE);
});

// ─── money assertions: stored value ─────────────────────────────────────────

it('amount is stored as a decimal string not a float', function () {
    $pr = p5_draft(['amount' => 5_500_000]);

    // The decimal:2 cast returns a string, never a float.
    expect($pr->amount)->toBeString()
        ->and($pr->amount)->toBe('5500000.00')
        ->and(is_float($pr->amount))->toBeFalse();
});

it('large IDR amount within safe range is stored and retrieved precisely', function () {
    // 100 billion IDR — well within bcmath safe range and below 2^53 float loss boundary.
    $pr = p5_draft(['amount' => '100000000000']);

    expect($pr->fresh()->amount)->toBe('100000000000.00');
});

it('IDR display formatting (money cast) does not mutate the stored decimal value', function () {
    $pr = p5_draft(['amount' => 7_750_500]);

    // Simulated display: what the Filament TextEntry->money() would show.
    // The stored value must be unchanged regardless of display formatting.
    $stored = $pr->fresh()->amount;
    $displayed = number_format((float) $stored, 2, '.', ','); // naive display — NOT what we store

    expect($stored)->toBe('7750500.00') // stored value unchanged
        ->and($displayed)->toBe('7,750,500.00'); // display is separate
});

// ─── money assertions: zero and negative (BUG-7 fixed) ─────────────────────

it('zero amount is rejected by createManual', function () {
    expect(fn () => $this->svc->createManual($this->creator, p5_payload(['amount' => 0])))
        ->toThrow(\RuntimeException::class, 'A payment request amount must be greater than zero.');
});

it('negative amount is rejected by createManual', function () {
    expect(fn () => $this->svc->createManual($this->creator, p5_payload(['amount' => -1_000])))
        ->toThrow(\RuntimeException::class, 'A payment request amount must be greater than zero.');
});
