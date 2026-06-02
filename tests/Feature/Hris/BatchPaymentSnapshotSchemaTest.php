<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Support\Finance\PaymentBundleSnapshot;

beforeEach(function () {
    $this->employee = Employee::factory()->create([
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
    ]);
});

it('builds the required versioned weekly allowance snapshot without exposing full bank account in public payees', function () {
    $batch = WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => WeeklyAllowanceBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => '125000.00',
    ]);

    WeeklyAllowancePayment::create([
        'weekly_allowance_batch_id' => $batch->id,
        'employee_id' => $this->employee->id,
        'daily_rate' => '25000.00',
        'days_present' => 5,
        'total_amount' => '125000.00',
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => WeeklyAllowancePayment::STATUS_APPROVED,
    ]);

    $snapshot = PaymentBundleSnapshot::forWeeklyAllowanceBatch($batch->fresh());

    expect($snapshot['snapshot_version'])->toBe(1);
    expect($snapshot['source_type'])->toBe(PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH);
    expect($snapshot['source_status'])->toBe(WeeklyAllowanceBatch::STATUS_PAID);
    expect($snapshot['currency'])->toBe('IDR');
    expect($snapshot['total_amount'])->toBe('125000.00');
    expect($snapshot['payable_count'])->toBe(1);
    expect($snapshot['summary']['week_label'])->toContain('25 May');
    expect($snapshot['payees'][0])->toMatchArray([
        'payee_id' => (string) $this->employee->id,
        'payee_name' => 'Ayu Finance',
        'bank_name' => 'BCA',
        'bank_account_last4' => '3210',
        'bank_account_encrypted_ref' => 'payee:'.$this->employee->id,
        'amount' => '125000.00',
        'currency' => 'IDR',
    ]);
    expect($snapshot['payees'][0])->not->toHaveKey('bank_account_number');
    expect($snapshot['protected_payees'][0]['bank_account_number'])->toBe('9876543210');
    expect($snapshot['source_hash'])->toBe(PaymentBundleSnapshot::hash($snapshot));
});

it('builds versioned overtime and late-pay snapshots using the same schema', function () {
    $overtime = OvertimePayBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => OvertimePayBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => '75000.00',
    ]);
    OvertimePayment::create([
        'overtime_pay_batch_id' => $overtime->id,
        'employee_id' => $this->employee->id,
        'total_sessions' => 2,
        'total_duration_minutes' => 180,
        'total_amount' => '75000.00',
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => OvertimePayment::STATUS_APPROVED,
    ]);

    $late = LatePayCompensationBatch::create([
        'scheduled_pay_date' => '2026-05-30',
        'actual_pay_date' => '2026-06-02',
        'days_late' => 3,
        'rate_percent' => '1.00',
        'status' => LatePayCompensationBatch::STATUS_PAID,
        'payment_status' => PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE,
        'total_employees' => 1,
        'total_amount' => '50000.00',
    ]);
    LatePayCompensationItem::create([
        'late_pay_compensation_batch_id' => $late->id,
        'employee_id' => $this->employee->id,
        'basic_salary_snapshot' => '5000000.00',
        'days_late' => 3,
        'rate_percent' => '1.00',
        'total_amount' => '50000.00',
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'Ayu Finance',
        'status' => LatePayCompensationItem::STATUS_APPROVED,
    ]);

    foreach ([PaymentBundleSnapshot::forOvertimePayBatch($overtime->fresh()), PaymentBundleSnapshot::forLatePayCompensationBatch($late->fresh())] as $snapshot) {
        expect($snapshot['snapshot_version'])->toBe(1);
        expect($snapshot['currency'])->toBe('IDR');
        expect($snapshot['payable_count'])->toBe(1);
        expect($snapshot['payees'][0]['bank_account_last4'])->toBe('3210');
        expect($snapshot['payees'][0])->not->toHaveKey('bank_account_number');
        expect($snapshot['protected_payees'][0]['bank_account_number'])->toBe('9876543210');
        expect($snapshot['source_hash'])->toBe(PaymentBundleSnapshot::hash($snapshot));
    }
});
