<?php

/**
 * Phase 15 — PaymentAuthorizationSettings page direct authorization tests.
 *
 * Gate: payment_bundles.manage_signers permission.
 * Enforcement: mountCanAuthorizeAccess() lifecycle hook → abort_unless(canAccess(), 403).
 *
 * Overlap: FinanceFilamentAccessTest covers PaymentApprovalView canAccess()
 * static check only. No test covers PaymentAuthorizationSettings page access.
 * This file adds net-new coverage.
 */

use App\Filament\Pages\PaymentAuthorizationSettings;
use App\Models\Hris\PaymentAuthorizationSetting;
use Livewire\Livewire;
use Tests\Support\FinanceCenterFlowFactory;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    FinanceCenterFlowFactory::seedFinanceRolesAndPermissions();
    // Ensure a settings record exists for page mount
    PaymentAuthorizationSetting::current();
});

// ─── canAccess: static check ──────────────────────────────────────────────────

it('finance_creator (has manage_signers perm) can access PaymentAuthorizationSettings', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();
    actingAs($creator);

    expect(PaymentAuthorizationSettings::canAccess())->toBeTrue();
})->group('p15-settings');

it('finance_executor (no manage_signers perm) cannot access PaymentAuthorizationSettings', function () {
    $executor = FinanceCenterFlowFactory::financeExecutor();
    actingAs($executor);

    expect(PaymentAuthorizationSettings::canAccess())->toBeFalse();
})->group('p15-settings');

it('finance_reviewer (no manage_signers perm) cannot access PaymentAuthorizationSettings', function () {
    $reviewer = FinanceCenterFlowFactory::financeReviewer();
    actingAs($reviewer);

    expect(PaymentAuthorizationSettings::canAccess())->toBeFalse();
})->group('p15-settings');

it('COO (no manage_signers perm) cannot access PaymentAuthorizationSettings', function () {
    $coo = FinanceCenterFlowFactory::coo();
    actingAs($coo);

    expect(PaymentAuthorizationSettings::canAccess())->toBeFalse();
})->group('p15-settings');

// ─── Livewire mount: unauthorized user gets 403 ───────────────────────────────

it('finance_executor Livewire mount of PaymentAuthorizationSettings is aborted (403)', function () {
    $executor = FinanceCenterFlowFactory::financeExecutor();

    Livewire::actingAs($executor)
        ->test(PaymentAuthorizationSettings::class)
        ->assertStatus(403);
})->group('p15-settings');

it('finance_reviewer Livewire mount of PaymentAuthorizationSettings is aborted (403)', function () {
    $reviewer = FinanceCenterFlowFactory::financeReviewer();

    Livewire::actingAs($reviewer)
        ->test(PaymentAuthorizationSettings::class)
        ->assertStatus(403);
})->group('p15-settings');

// ─── Livewire mount: authorized user loads successfully ───────────────────────

it('finance_creator can mount PaymentAuthorizationSettings (authorized)', function () {
    $creator = FinanceCenterFlowFactory::financeCreator();

    Livewire::actingAs($creator)
        ->test(PaymentAuthorizationSettings::class)
        ->assertSuccessful();
})->group('p15-settings');

// ─── save action: unauthorized user cannot call save (action-level block) ─────
// Even if an unauthorized user could somehow mount the page, the page-level 403
// prevents it entirely (abort_unless on mount). This test documents the boundary.

it('finance_executor cannot call save on PaymentAuthorizationSettings (page-level block prevents mount)', function () {
    $executor = FinanceCenterFlowFactory::financeExecutor();

    // Assert the page is inaccessible at the static level
    actingAs($executor);
    expect(PaymentAuthorizationSettings::canAccess())->toBeFalse();

    // Verify Livewire mount also fails
    Livewire::actingAs($executor)
        ->test(PaymentAuthorizationSettings::class)
        ->assertStatus(403);
})->group('p15-settings');
