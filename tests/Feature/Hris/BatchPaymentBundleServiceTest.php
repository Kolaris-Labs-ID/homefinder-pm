<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundleService;

beforeEach(function () {
    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->actor = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
    ]);
});

it('adds weekly allowance, overtime, and late-pay batches as canonical payment-bundled sources', function () {
    $bundle = $this->bundles->create($this->actor);

    $weekly = makeBatchWeeklyAllowance($this->employee, '125000.00');
    $overtime = makeBatchOvertime($this->employee, '75000.00');
    $latePay = makeBatchLatePay($this->employee, '50000.00');

    $weeklyItem = $this->batchBundles->addSource($bundle, $weekly, $this->actor);
    $overtimeItem = $this->batchBundles->addSource($bundle->fresh(), $overtime, $this->actor);
    $lateItem = $this->batchBundles->addSource($bundle->fresh(), $latePay, $this->actor);

    expect($weeklyItem->status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    expect($overtimeItem->source_type)->toBe(PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH);
    expect($lateItem->source_type)->toBe(PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH);

    expect($weekly->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    expect($overtime->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    expect($latePay->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);

    expect(bccomp((string) $bundle->fresh()->total_amount, '250000.00', 2))->toBe(0);
});

it('blocks sources with missing payable bank data and marks them payment_blocked', function () {
    $bundle = $this->bundles->create($this->actor);
    $weekly = makeBatchWeeklyAllowance($this->employee, '125000.00', bankAccount: null);

    expect(fn () => $this->batchBundles->addSource($bundle, $weekly, $this->actor))
        ->toThrow(RuntimeException::class, 'missing payable data');

    expect($weekly->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BLOCKED);
});

it('blocks adding the same source to two active bundles', function () {
    $source = makeBatchWeeklyAllowance($this->employee, '125000.00');
    $bundleA = $this->bundles->create($this->actor);
    $bundleB = $this->bundles->create($this->actor);

    $this->batchBundles->addSource($bundleA, $source, $this->actor);

    expect(fn () => $this->batchBundles->addSource($bundleB, $source->fresh(), $this->actor))
        ->toThrow(RuntimeException::class, 'already in an active bundle');
});

function makeBatchWeeklyAllowance(Employee $employee, string $amount, ?string $bankAccount = '9876543210'): WeeklyAllowanceBatch
{
    $batch = WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => WeeklyAllowanceBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => $amount,
    ]);

    WeeklyAllowancePayment::create([
        'weekly_allowance_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'daily_rate' => $amount,
        'days_present' => 1,
        'total_amount' => $amount,
        'bank_name' => $bankAccount ? 'BCA' : null,
        'bank_account_number' => $bankAccount,
        'bank_account_holder' => $bankAccount ? 'Ayu Finance' : null,
        'status' => WeeklyAllowancePayment::STATUS_APPROVED,
    ]);

    return $batch;
}

function makeBatchOvertime(Employee $employee, string $amount): OvertimePayBatch
{
    $batch = OvertimePayBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => OvertimePayBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => $amount,
    ]);

    OvertimePayment::create([
        'overtime_pay_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'total_sessions' => 1,
        'total_duration_minutes' => 60,
        'total_amount' => $amount,
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => OvertimePayment::STATUS_APPROVED,
    ]);

    return $batch;
}

function makeBatchLatePay(Employee $employee, string $amount): LatePayCompensationBatch
{
    $batch = LatePayCompensationBatch::create([
        'scheduled_pay_date' => '2026-05-30',
        'actual_pay_date' => '2026-06-02',
        'days_late' => 3,
        'rate_percent' => '1.00',
        'status' => LatePayCompensationBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => $amount,
    ]);

    LatePayCompensationItem::create([
        'late_pay_compensation_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'basic_salary_snapshot' => '5000000.00',
        'days_late' => 3,
        'rate_percent' => '1.00',
        'total_amount' => $amount,
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => LatePayCompensationItem::STATUS_APPROVED,
    ]);

    return $batch;
}
