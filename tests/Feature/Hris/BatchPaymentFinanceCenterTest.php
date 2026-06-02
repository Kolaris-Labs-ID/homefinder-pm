<?php

use App\Filament\Pages\FinanceCenter;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Database\Seeders\FinanceRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Phase 12 — Finance Center batch modules: per-state source counts, an honestly-disabled payroll
 * module, and a summary that exposes counts only (never bank/payee detail).
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function fcWeeklyAllowance(string $paymentStatus): WeeklyAllowanceBatch
{
    return WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => 'paid',
        'payment_status' => $paymentStatus,
        'total_employees' => 1,
        'total_amount' => '100000.00',
    ]);
}

it('counts eligible, bundled, and blocked sources per module', function () {
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE);
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE);
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_BLOCKED);
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_PAID);

    $summary = (new FinanceCenter)->batchPaymentModules()['weekly_allowance']['summary'];

    expect($summary['eligible'])->toBe(2);
    expect($summary['bundled'])->toBe(1);
    expect($summary['blocked'])->toBe(1);
    expect($summary['paid'])->toBe(1);
});

it('keeps the payroll module honestly disabled with a reason', function () {
    $modules = (new FinanceCenter)->batchPaymentModules();

    expect($modules['weekly_allowance']['enabled'])->toBeTrue();
    expect($modules['overtime_pay']['enabled'])->toBeTrue();
    expect($modules['late_pay_compensation']['enabled'])->toBeTrue();
    expect($modules['payroll_payments']['enabled'])->toBeFalse();
    expect($modules['payroll_payments'])->toHaveKey('disabled_reason');
});

it('exposes only aggregate counts in the module summary, never bank or payee detail', function () {
    fcWeeklyAllowance(PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE);

    $summary = (new FinanceCenter)->batchPaymentModules()['weekly_allowance']['summary'];

    expect(array_keys($summary))->toBe(['eligible', 'bundled', 'blocked', 'authorized', 'recording', 'paid']);
    foreach ($summary as $value) {
        expect($value)->toBeInt();
    }
});

it('denies Finance Center access to a user without finance permission', function () {
    actingAs(User::factory()->create());

    expect(FinanceCenter::canAccess())->toBeFalse();
});
