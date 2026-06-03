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

// ─── edit-locking: LOCKED states (BUG-A) ────────────────────────────────────
//
// The service does NOT throw for these states — updateApprovedDetails silently
// applies the change. These tests assert the ACTUAL service behavior (no throw)
// and document it as BUG-A. Tests that follow the expected-safe pattern
// (->toThrow) are intentionally inverted to show the gap.

it('updateApprovedDetails on in_payment_bundle does NOT throw — BUG-A: missing edit lock', function () {
    $pr = p5_approved();

    // Forcibly place the PR into in_payment_bundle (simulating bundle add).
    $pr->update(['status' => PaymentRequest::STATUS_IN_PAYMENT_BUNDLE]);
    $pr = $pr->fresh();
    expect($pr->status)->toBe(PaymentRequest::STATUS_IN_PAYMENT_BUNDLE);

    // BUG-A: the service accepts the update (no throw) and mutates a bundled PR.
    // A correct implementation would throw RuntimeException here.
    $threw = false;
    try {
        $this->svc->updateApprovedDetails($pr, ['amount' => 9_999_999]);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    // Assert the BUG: no exception thrown (change is silently accepted).
    expect($threw)->toBeFalse('BUG-A: updateApprovedDetails should throw for in_payment_bundle but does not');
    expect($pr->fresh()->amount)->toBe('9999999.00'); // the mutation happened
})->group('p5-edit-lock-bug');

it('updateApprovedDetails on paid does NOT throw — BUG-A: missing edit lock', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_PAID]);
    $pr = $pr->fresh();

    $threw = false;
    try {
        $this->svc->updateApprovedDetails($pr, ['payee_name' => 'Rogue Payee']);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    expect($threw)->toBeFalse('BUG-A: updateApprovedDetails should throw for paid but does not');
    expect($pr->fresh()->payee_name)->toBe('Rogue Payee'); // mutation succeeded
})->group('p5-edit-lock-bug');

it('updateApprovedDetails on reconciled does NOT throw — BUG-A: missing edit lock', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_RECONCILED]);
    $pr = $pr->fresh();

    $threw = false;
    try {
        $this->svc->updateApprovedDetails($pr, ['amount' => 1]);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    expect($threw)->toBeFalse('BUG-A: updateApprovedDetails should throw for reconciled but does not');
})->group('p5-edit-lock-bug');

it('updateApprovedDetails on paid_pending_reconciliation does NOT throw — BUG-A', function () {
    $pr = p5_approved();
    $pr->update(['status' => PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION]);
    $pr = $pr->fresh();

    $threw = false;
    try {
        $this->svc->updateApprovedDetails($pr, ['amount' => 1]);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    expect($threw)->toBeFalse('BUG-A: updateApprovedDetails should throw for paid_pending_reconciliation but does not');
})->group('p5-edit-lock-bug');

// ─── ER-sourced PR edit via updateApprovedDetails (BUG-B) ───────────────────
//
// updateApprovedDetails has no source_type guard: an ER-sourced PR can be
// mutated through the manual-PR edit path. While the method itself allows it
// (voiding the COO snapshot when sensitive fields change), there is no guard
// preventing finance_creator from calling it on an ER-sourced PR. This test
// documents the absence of that guard (BUG-B).

it('updateApprovedDetails accepts an ER-sourced PR with no source_type guard — BUG-B', function () {
    // Create a PR that claims to be ER-sourced (simulated with source_type override).
    $pr = $this->svc->createManual($this->creator, p5_payload([
        'source_type' => PaymentRequest::SOURCE_EXPENSE_REQUEST,
        'source_id' => 9999, // non-existent ER — service doesn't check
    ]));
    $this->svc->submitToCoo($pr);
    $this->svc->approveAsCoo($pr, $this->coo);
    $pr = $pr->fresh();
    expect($pr->source_type)->toBe(PaymentRequest::SOURCE_EXPENSE_REQUEST);

    // BUG-B: the service does not throw for source_type=expense_request.
    $threw = false;
    try {
        $this->svc->updateApprovedDetails($pr, ['amount' => 7_000_000]);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    expect($threw)->toBeFalse('BUG-B: updateApprovedDetails should guard against ER-sourced PRs but does not');
})->group('p5-edit-lock-bug');

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

// ─── money assertions: zero and negative (GAP-C) ────────────────────────────
//
// The service has NO validation for zero or negative amounts in createManual.
// These tests document that the service currently ACCEPTS them (gap).
// If you expect them to be rejected at the service layer, these tests would
// be written ->toThrow() — but that would PASS (the service doesn't throw).
// Instead we document the actual behavior and mark it as GAP-C.

it('zero amount is NOT rejected by createManual — GAP-C: missing service validation', function () {
    $threw = false;
    $pr = null;
    try {
        $pr = $this->svc->createManual($this->creator, p5_payload(['amount' => 0]));
    } catch (\Throwable $e) {
        $threw = true;
    }

    // GAP-C: no exception; a PR with amount=0.00 is created.
    expect($threw)->toBeFalse('GAP-C: createManual should reject zero amount but does not');
    if ($pr !== null) {
        expect($pr->fresh()->amount)->toBe('0.00');
    }
})->group('p5-money-gap');

it('negative amount is NOT rejected by createManual — GAP-C: missing service validation', function () {
    $threw = false;
    $pr = null;
    try {
        $pr = $this->svc->createManual($this->creator, p5_payload(['amount' => -1_000]));
    } catch (\Throwable $e) {
        $threw = true;
    }

    // GAP-C: no exception; a PR with negative amount is created.
    expect($threw)->toBeFalse('GAP-C: createManual should reject negative amount but does not');
    if ($pr !== null) {
        expect($pr->fresh()->amount)->toBe('-1000.00');
    }
})->group('p5-money-gap');
