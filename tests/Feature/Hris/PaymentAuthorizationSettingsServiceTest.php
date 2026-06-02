<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\User;
use App\Services\Hris\PaymentAuthorizationSettingsService;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->svc = app(PaymentAuthorizationSettingsService::class);

    $this->f1 = User::factory()->create();
    $this->f1->assignRole('finance_signer');

    $this->f2 = User::factory()->create();
    $this->f2->assignRole('finance_signer');

    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');
});

function settingsWith(array $attrs): PaymentAuthorizationSetting
{
    $s = PaymentAuthorizationSetting::current();
    $s->fill($attrs);

    return $s;
}

it('validate() passes and isComplete() is true for valid settings with all three distinct signers', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->exec->id,
        'require_two_finance_signatures' => true,
        'require_executive_signature' => true,
        'high_value_threshold' => '10000000.00',
    ]);

    expect(fn () => test()->svc->validate($s))->not->toThrow(RuntimeException::class);
    expect(test()->svc->isComplete($s))->toBeTrue();
});

it('assignments() returns all three slot-keyed user ids when all signers are set', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    $result = test()->svc->assignments($s);

    expect($result)->toBe([
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->f1->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->f2->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ]);
});

it('throws permission message for Finance Signer 1 lacking sign.finance', function () {
    $noPerm = User::factory()->create();
    $s = settingsWith([
        'finance_signer_1_user_id' => $noPerm->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'Finance Signer 1 lacks the required permission (payment_bundles.sign.finance).');
});

it('throws permission message for Finance Signer 2 lacking sign.finance', function () {
    $noPerm = User::factory()->create();
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => $noPerm->id,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'Finance Signer 2 lacks the required permission (payment_bundles.sign.finance).');
});

it('throws permission message for Executive Signer lacking sign.executive', function () {
    $noPerm = User::factory()->create();
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => $noPerm->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'Executive Signer lacks the required permission (payment_bundles.sign.executive).');
});

it('throws when Finance Signer 1 and Finance Signer 2 are the same user', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f1->id,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'The two finance signers must be different people.');
});

it('throws when Executive Signer equals Finance Signer 1', function () {
    $this->f1->assignRole('executive_signer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->f1->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'The executive signer must differ from both finance signers.');
});

it('throws when Executive Signer equals Finance Signer 2', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->f2->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'The executive signer must differ from both finance signers.');
});

it('throws when Finance Signer 1 is not assigned', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => null,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'Finance Signer 1 must be assigned.');
});

it('passes when require_two_finance_signatures is false and F2 is null', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => null,
        'executive_signer_user_id' => test()->exec->id,
        'require_two_finance_signatures' => false,
    ]);

    expect(fn () => test()->svc->validate($s))->not->toThrow(RuntimeException::class);
    expect(test()->svc->isComplete($s))->toBeTrue();
});

it('throws when require_two_finance_signatures is true and F2 is null', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => null,
        'executive_signer_user_id' => test()->exec->id,
        'require_two_finance_signatures' => true,
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'Finance Signer 2 must be assigned.');
});

it('passes when require_executive_signature is false and Executive is null', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => null,
        'require_executive_signature' => false,
    ]);

    expect(fn () => test()->svc->validate($s))->not->toThrow(RuntimeException::class);
    expect(test()->svc->isComplete($s))->toBeTrue();
});

it('throws when high_value_threshold is negative', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => test()->f2->id,
        'executive_signer_user_id' => test()->exec->id,
        'high_value_threshold' => '-1.00',
    ]);

    expect(fn () => test()->svc->validate($s))
        ->toThrow(RuntimeException::class, 'The high-value threshold must be zero or greater.');
});

it('the three payment bundle permissions exist after seeding', function () {
    expect(Permission::where('name', 'payment_bundles.sign.finance')->exists())->toBeTrue();
    expect(Permission::where('name', 'payment_bundles.sign.executive')->exists())->toBeTrue();
    expect(Permission::where('name', 'payment_bundles.manage_signers')->exists())->toBeTrue();
});

it('assignments() omits slots with null user ids', function () {
    $s = settingsWith([
        'finance_signer_1_user_id' => test()->f1->id,
        'finance_signer_2_user_id' => null,
        'executive_signer_user_id' => test()->exec->id,
    ]);

    $result = test()->svc->assignments($s);

    expect($result)->toHaveKey(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value);
    expect($result)->not->toHaveKey(PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value);
    expect($result)->toHaveKey(PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value);
});

it('current() returns the PaymentAuthorizationSetting singleton', function () {
    expect(test()->svc->current())->toBeInstanceOf(PaymentAuthorizationSetting::class);
    expect(test()->svc->current()->id)->toBe(PaymentAuthorizationSetting::current()->id);
});
