<?php

use App\Filament\Pages\PaymentApprovalView;
use App\Filament\Resources\ExpenseRequestResource;
use App\Filament\Resources\PaymentRequestResource;
use App\Models\User;
use Database\Seeders\FinanceRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Phase 12 — Filament access gating for the payment approval view + ER/PR resources.
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('grants the payment approval view to a COO (has payment_requests.approve)', function () {
    $coo = User::factory()->create();
    $coo->assignRole('COO');
    actingAs($coo);

    expect(PaymentApprovalView::canAccess())->toBeTrue();
});

it('denies the payment approval view to a finance creator (no payment_requests.approve)', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');
    actingAs($user);

    expect(PaymentApprovalView::canAccess())->toBeFalse();
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
