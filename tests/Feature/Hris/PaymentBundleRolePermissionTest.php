<?php

use App\Enums\PaymentBundleSignatureSlot;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 2 — Payment Bundle Signature Permissions & Roles.
 * Verifies the 14-permission set, signer roles, and the PaymentBundleSignatureSlot enum.
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

$ALL_PERMISSIONS = [
    'payment_bundles.view',
    'payment_bundles.create',
    'payment_bundles.update_draft',
    'payment_bundles.submit_for_signature',
    'payment_bundles.sign.finance',
    'payment_bundles.sign.executive',
    'payment_bundles.reject_signature',
    'payment_bundles.mark_paid',
    'payment_bundles.reject_payment_proof',
    'payment_bundles.void',
    'payment_bundles.cancel',
    'payment_bundles.download_pdf',
    'payment_bundles.manage_signers',
    'payment_bundles.verify_document',
];

it('seeds all 14 payment_bundles permissions on the web guard', function () use ($ALL_PERMISSIONS) {
    foreach ($ALL_PERMISSIONS as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())
            ->toBeTrue("missing permission: {$perm}");
    }
    expect(Permission::where('guard_name', 'web')->where('name', 'LIKE', 'payment_bundles.%')->count())
        ->toBe(14);
});

it('seeds finance_signer and executive_signer roles', function () {
    foreach (['finance_signer', 'executive_signer'] as $role) {
        expect(Role::where('name', $role)->where('guard_name', 'web')->exists())
            ->toBeTrue("missing role: {$role}");
    }
});

it('grants finance_signer sign.finance but never sign.executive', function () {
    $role = Role::findByName('finance_signer', 'web');
    expect($role->hasPermissionTo('payment_bundles.sign.finance'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.reject_signature'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.download_pdf'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.verify_document'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.sign.executive'))->toBeFalse();
});

it('grants executive_signer sign.executive but never sign.finance', function () {
    $role = Role::findByName('executive_signer', 'web');
    expect($role->hasPermissionTo('payment_bundles.sign.executive'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.reject_signature'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.download_pdf'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.verify_document'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.sign.finance'))->toBeFalse();
});

it('grants finance_creator bundle lifecycle powers but never signing or paying', function () {
    $role = Role::findByName('finance_creator', 'web');
    expect($role->hasPermissionTo('payment_bundles.create'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.submit_for_signature'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.manage_signers'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.cancel'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.void'))->toBeTrue();
    // Segregation: creator cannot sign or pay.
    expect($role->hasPermissionTo('payment_bundles.sign.finance'))->toBeFalse();
    expect($role->hasPermissionTo('payment_bundles.sign.executive'))->toBeFalse();
    expect($role->hasPermissionTo('payment_bundles.mark_paid'))->toBeFalse();
});

it('grants finance_executor mark_paid and reject_payment_proof but never signing or creating', function () {
    $role = Role::findByName('finance_executor', 'web');
    expect($role->hasPermissionTo('payment_bundles.mark_paid'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.reject_payment_proof'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.download_pdf'))->toBeTrue();
    expect($role->hasPermissionTo('payment_bundles.verify_document'))->toBeTrue();
    // Segregation: executor cannot sign or create bundles.
    expect($role->hasPermissionTo('payment_bundles.sign.finance'))->toBeFalse();
    expect($role->hasPermissionTo('payment_bundles.sign.executive'))->toBeFalse();
    expect($role->hasPermissionTo('payment_bundles.create'))->toBeFalse();
});

it('is idempotent — re-seeding does not duplicate permissions', function () {
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    expect(Permission::where('name', 'payment_bundles.sign.finance')->count())->toBe(1);
    expect(Role::where('name', 'finance_signer')->count())->toBe(1);
    expect(Role::where('name', 'executive_signer')->count())->toBe(1);
});

// --- PaymentBundleSignatureSlot enum ---

it('FINANCE_SIGNER_1 requires payment_bundles.sign.finance', function () {
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->requiredPermission())
        ->toBe('payment_bundles.sign.finance');
});

it('FINANCE_SIGNER_2 requires payment_bundles.sign.finance', function () {
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_2->requiredPermission())
        ->toBe('payment_bundles.sign.finance');
});

it('EXECUTIVE_SIGNER requires payment_bundles.sign.executive', function () {
    expect(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->requiredPermission())
        ->toBe('payment_bundles.sign.executive');
});

it('isFinance returns true for both finance slots and false for executive', function () {
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->isFinance())->toBeTrue();
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_2->isFinance())->toBeTrue();
    expect(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->isFinance())->toBeFalse();
});

it('order returns 1, 2, 3 for the three slots', function () {
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->order())->toBe(1);
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_2->order())->toBe(2);
    expect(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->order())->toBe(3);
});

it('ordered() returns the three slots in signing order', function () {
    $ordered = PaymentBundleSignatureSlot::ordered();
    expect($ordered)->toHaveCount(3);
    expect($ordered[0])->toBe(PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    expect($ordered[1])->toBe(PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    expect($ordered[2])->toBe(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
});

it('label() returns human-readable strings', function () {
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->label())->toBe('Finance Signer 1');
    expect(PaymentBundleSignatureSlot::FINANCE_SIGNER_2->label())->toBe('Finance Signer 2');
    expect(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->label())->toBe('Executive Signer');
});
