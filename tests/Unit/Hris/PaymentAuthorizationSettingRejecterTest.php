<?php

use App\Models\Hris\PaymentAuthorizationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports configured users as PR rejecters', function () {
    $settings = PaymentAuthorizationSetting::current();
    $settings->update(['pr_rejecter_user_ids' => [7, 42]]);

    expect($settings->fresh()->isPrRejecter(7))->toBeTrue()
        ->and($settings->fresh()->isPrRejecter(42))->toBeTrue()
        ->and($settings->fresh()->isPrRejecter(99))->toBeFalse();
})->group('pr-rejecter');

it('treats a null/empty allowlist as nobody', function () {
    $settings = PaymentAuthorizationSetting::current();

    expect($settings->isPrRejecter(1))->toBeFalse();
})->group('pr-rejecter');

it('normalizes string-stored IDs to ints', function () {
    $settings = PaymentAuthorizationSetting::current();
    $settings->update(['pr_rejecter_user_ids' => ['7', '42']]);

    expect($settings->fresh()->isPrRejecter(7))->toBeTrue()
        ->and($settings->fresh()->isPrRejecter(42))->toBeTrue();
})->group('pr-rejecter');
