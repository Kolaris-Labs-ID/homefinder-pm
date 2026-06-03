<?php

use Spatie\Permission\Models\Role;
use Tests\Support\FinanceCenterFlowFactory;

/**
 * Phase 16 — Smoke test for FinanceCenterFlowFactory (real seeders, real permissions).
 * Proves the helper wires up correctly before other finance test phases depend on it.
 */
beforeEach(function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
});

it('seeds all required finance permissions on the web guard', function () {
    FinanceCenterFlowFactory::assertRequiredPermissionsExist();
});

it('finance_creator can payment_requests.create but not payment_requests.approve', function () {
    $user = FinanceCenterFlowFactory::financeCreator();

    expect($user->can('payment_requests.create'))->toBeTrue();
    expect($user->can('payment_requests.approve'))->toBeFalse();
});

it('COO can payment_requests.approve but not payment_requests.create', function () {
    $user = FinanceCenterFlowFactory::coo();

    expect($user->can('payment_requests.approve'))->toBeTrue();
    expect($user->can('payment_requests.create'))->toBeFalse();
});

it('finance_signer can payment_bundles.sign.finance but not payment_bundles.sign.executive', function () {
    $user = FinanceCenterFlowFactory::financeSigner();

    expect($user->can('payment_bundles.sign.finance'))->toBeTrue();
    expect($user->can('payment_bundles.sign.executive'))->toBeFalse();
});

it('userWithRole rejects a fictional role name', function () {
    expect(fn () => FinanceCenterFlowFactory::userWithRole('fictional_role_xyz'))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('convenience wrappers assign real seeded roles', function () {
    $reviewer = FinanceCenterFlowFactory::financeReviewer();
    $executor = FinanceCenterFlowFactory::financeExecutor();
    $reconciler = FinanceCenterFlowFactory::financeReconciler();
    $financeSigner = FinanceCenterFlowFactory::financeSigner();
    $execSigner = FinanceCenterFlowFactory::executiveSigner();

    expect($reviewer->hasRole('finance_reviewer'))->toBeTrue();
    expect($executor->hasRole('finance_executor'))->toBeTrue();
    expect($reconciler->hasRole('finance_reconciler'))->toBeTrue();
    expect($financeSigner->hasRole('finance_signer'))->toBeTrue();
    expect($execSigner->hasRole('executive_signer'))->toBeTrue();
});

it('seedFinanceRolesAndPermissions is idempotent', function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();

    expect(Role::where('name', 'finance_creator')->where('guard_name', 'web')->count())->toBe(1);
    expect(Role::where('name', 'finance_signer')->where('guard_name', 'web')->count())->toBe(1);
});
