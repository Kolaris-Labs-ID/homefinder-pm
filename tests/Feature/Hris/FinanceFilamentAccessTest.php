<?php

use App\Filament\Pages\CooApprovalView;
use App\Filament\Resources\ExpenseRequestResource;
use App\Filament\Resources\PaymentRequestResource;
use App\Models\User;
use Database\Seeders\FinanceRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Phase 12 — Filament access gating for the COO view + ER/PR resources.
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('grants the COO approval view to a COO (permission + C-level)', function () {
    $coo = User::factory()->create();
    $coo->assignRole('COO');
    actingAs($coo);

    expect(CooApprovalView::canAccess())->toBeTrue();
});

it('denies the COO approval view to a finance creator (no approve_as_coo, not C-level)', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');
    actingAs($user);

    expect(CooApprovalView::canAccess())->toBeFalse();
});

it('gates ER and PR resources on the right finance permission', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole('finance_reviewer'); // has expense_requests.view_all, not payment_requests.view_all
    actingAs($reviewer);

    expect(ExpenseRequestResource::canViewAny())->toBeTrue();
    expect(PaymentRequestResource::canViewAny())->toBeFalse();

    $creator = User::factory()->create();
    $creator->assignRole('finance_creator'); // has payment_requests.view_all
    actingAs($creator);

    expect(PaymentRequestResource::canViewAny())->toBeTrue();
});
