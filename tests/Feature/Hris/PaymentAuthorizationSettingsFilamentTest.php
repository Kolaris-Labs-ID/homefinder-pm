<?php

use App\Filament\Pages\PaymentAuthorizationSettings;
use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\User;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->f1 = User::factory()->create();
    $this->f1->assignRole('finance_signer');

    $this->f2 = User::factory()->create();
    $this->f2->assignRole('finance_signer');

    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');

    $this->plain = User::factory()->create();
});

it('canAccess() returns true for a user with payment_bundles.manage_signers', function () {
    $manager = User::factory()->create();
    $manager->assignRole('finance_creator');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($manager);

    expect(PaymentAuthorizationSettings::canAccess())->toBeTrue();
});

it('canAccess() returns true for super_admin', function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($superAdmin);

    expect(PaymentAuthorizationSettings::canAccess())->toBeTrue();
});

it('canAccess() returns false for a plain user without the permission', function () {
    actingAs($this->plain);

    expect(PaymentAuthorizationSettings::canAccess())->toBeFalse();
});

it('save() persists valid settings to the singleton', function () {
    $manager = User::factory()->create();
    $manager->assignRole('finance_creator');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($manager);

    $page = new PaymentAuthorizationSettings;
    $page->data = [
        'finance_signer_1_user_id' => $this->f1->id,
        'finance_signer_2_user_id' => $this->f2->id,
        'executive_signer_user_id' => $this->exec->id,
        'require_two_finance_signatures' => true,
        'require_executive_signature' => true,
        'high_value_threshold' => '5000000',
        'allow_creator_to_sign' => false,
        'allow_executor_to_sign' => false,
    ];

    $page->save();

    $current = PaymentAuthorizationSetting::current()->fresh();
    expect($current->finance_signer_1_user_id)->toBe($this->f1->id);
    expect($current->finance_signer_2_user_id)->toBe($this->f2->id);
    expect($current->executive_signer_user_id)->toBe($this->exec->id);
});

it('save() does not persist when a finance signer lacks the required permission', function () {
    $manager = User::factory()->create();
    $manager->assignRole('finance_creator');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($manager);

    $valid = new PaymentAuthorizationSettings;
    $valid->data = [
        'finance_signer_1_user_id' => $this->f1->id,
        'finance_signer_2_user_id' => $this->f2->id,
        'executive_signer_user_id' => $this->exec->id,
        'require_two_finance_signatures' => true,
        'require_executive_signature' => true,
        'high_value_threshold' => '5000000',
        'allow_creator_to_sign' => false,
        'allow_executor_to_sign' => false,
    ];
    $valid->save();

    $noPerm = User::factory()->create();

    $invalid = new PaymentAuthorizationSettings;
    $invalid->data = [
        'finance_signer_1_user_id' => $noPerm->id,
        'finance_signer_2_user_id' => $this->f2->id,
        'executive_signer_user_id' => $this->exec->id,
        'require_two_finance_signatures' => true,
        'require_executive_signature' => true,
        'high_value_threshold' => '5000000',
        'allow_creator_to_sign' => false,
        'allow_executor_to_sign' => false,
    ];
    $invalid->save();

    expect(PaymentAuthorizationSetting::current()->fresh()->finance_signer_1_user_id)
        ->toBe($this->f1->id);
});
