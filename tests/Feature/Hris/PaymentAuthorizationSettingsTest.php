<?php

use App\Models\Hris\PaymentAuthorizationSetting;

it('current() creates a row when none exists and returns it', function () {
    expect(PaymentAuthorizationSetting::count())->toBe(0);

    $setting = PaymentAuthorizationSetting::current();

    expect($setting)->toBeInstanceOf(PaymentAuthorizationSetting::class);
    expect($setting->exists)->toBeTrue();
    expect(PaymentAuthorizationSetting::count())->toBe(1);
});

it('current() returns the same singleton row on repeated calls', function () {
    $first = PaymentAuthorizationSetting::current();
    $second = PaymentAuthorizationSetting::current();

    expect(PaymentAuthorizationSetting::count())->toBe(1);
    expect($second->id)->toBe($first->id);
});

it('current() creates the row with safe defaults', function () {
    $setting = PaymentAuthorizationSetting::current();

    expect($setting->require_two_finance_signatures)->toBeTrue();
    expect($setting->require_executive_signature)->toBeTrue();
    expect($setting->allow_creator_to_sign)->toBeFalse();
    expect($setting->allow_executor_to_sign)->toBeFalse();
    expect($setting->finance_signer_1_user_id)->toBeNull();
    expect($setting->finance_signer_2_user_id)->toBeNull();
    expect($setting->executive_signer_user_id)->toBeNull();
});
