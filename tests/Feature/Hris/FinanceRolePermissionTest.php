<?php

use Database\Seeders\FinanceRolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 1 — Finance Roles & Permissions (EXPENSE_MODULE.MD).
 * Verifies the seeded permission set and the segregation-of-duties matrix.
 * Asserts at the role level via Spatie hasPermissionTo (bypasses the super_admin Gate::before).
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

$ALL_PERMISSIONS = [
    'expense_requests.view', 'expense_requests.view_all', 'expense_requests.create',
    'expense_requests.update_own_draft', 'expense_requests.submit', 'expense_requests.review',
    'expense_requests.verify', 'expense_requests.reject', 'expense_requests.request_revision',
    'expense_requests.cancel',
    'payment_requests.view', 'payment_requests.view_all', 'payment_requests.create',
    'payment_requests.submit_for_approval', 'payment_requests.approve', 'payment_requests.reject',
    'payment_requests.request_clarification', 'payment_requests.mark_paid', 'payment_requests.reconcile',
    'payment_requests.void', 'payment_requests.reverse',
    'finance_center.view', 'finance_center.view_sensitive_payment_data',
    'finance_center.view_payroll_placeholders', 'finance_center.manage_settings',
    'finance_attachments.upload', 'finance_attachments.download', 'finance_attachments.delete_own_unlocked',
];

it('seeds every finance permission on the web guard', function () use ($ALL_PERMISSIONS) {
    foreach ($ALL_PERMISSIONS as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())
            ->toBeTrue("missing permission: {$perm}");
    }
});

it('seeds the four finance roles', function () {
    foreach (['finance_reviewer', 'finance_creator', 'finance_executor', 'finance_reconciler'] as $role) {
        expect(Role::where('name', $role)->where('guard_name', 'web')->exists())
            ->toBeTrue("missing role: {$role}");
    }
});

it('grants finance_reviewer expense-review duties but no payment power', function () {
    $role = Role::findByName('finance_reviewer', 'web');
    expect($role->hasPermissionTo('expense_requests.verify'))->toBeTrue();
    expect($role->hasPermissionTo('expense_requests.reject'))->toBeTrue();
    expect($role->hasPermissionTo('expense_requests.request_revision'))->toBeTrue();
    expect($role->hasPermissionTo('expense_requests.view_all'))->toBeTrue();
    // No payment authority at all.
    expect($role->hasPermissionTo('payment_requests.create'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.approve'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.mark_paid'))->toBeFalse();
});

it('lets finance_creator create/submit PRs but not approve or pay', function () {
    $role = Role::findByName('finance_creator', 'web');
    expect($role->hasPermissionTo('payment_requests.create'))->toBeTrue();
    expect($role->hasPermissionTo('payment_requests.submit_for_approval'))->toBeTrue();
    expect($role->hasPermissionTo('expense_requests.view_all'))->toBeTrue();
    // Segregation: creator cannot self-approve, pay, or reconcile.
    expect($role->hasPermissionTo('payment_requests.approve'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.mark_paid'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.reconcile'))->toBeFalse();
});

it('lets finance_executor mark paid + upload proof but not reconcile', function () {
    $role = Role::findByName('finance_executor', 'web');
    expect($role->hasPermissionTo('payment_requests.mark_paid'))->toBeTrue();
    expect($role->hasPermissionTo('finance_attachments.upload'))->toBeTrue();
    // Segregation: executor cannot reconcile their own payment, approve, or create.
    expect($role->hasPermissionTo('payment_requests.reconcile'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.approve'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.create'))->toBeFalse();
});

it('lets finance_reconciler reconcile but not mark paid', function () {
    $role = Role::findByName('finance_reconciler', 'web');
    expect($role->hasPermissionTo('payment_requests.reconcile'))->toBeTrue();
    // Segregation: reconciler is not the payer.
    expect($role->hasPermissionTo('payment_requests.mark_paid'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.approve'))->toBeFalse();
});

it('grants COO approval power but never payment execution', function () {
    $role = Role::findByName('COO', 'web');
    expect($role->hasPermissionTo('payment_requests.approve'))->toBeTrue();
    expect($role->hasPermissionTo('payment_requests.reject'))->toBeTrue();
    expect($role->hasPermissionTo('finance_center.view_sensitive_payment_data'))->toBeTrue();
    // COO approves business, never moves money.
    expect($role->hasPermissionTo('payment_requests.mark_paid'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.reconcile'))->toBeFalse();
    expect($role->hasPermissionTo('payment_requests.create'))->toBeFalse();
});

it('is idempotent — re-seeding does not duplicate permissions', function () use ($ALL_PERMISSIONS) {
    $this->seed(FinanceRolePermissionSeeder::class);
    expect(Permission::where('name', 'payment_requests.approve')->count())->toBe(1);
    expect(Role::where('name', 'finance_creator')->count())->toBe(1);
});
