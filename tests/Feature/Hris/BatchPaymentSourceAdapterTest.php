<?php

use App\Models\Hris\PaymentBundleItem;
use App\Services\Hris\BatchPayments\LatePayCompensationBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\OvertimePayBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\PayrunBatchPaymentSourceAdapter;
use App\Services\Hris\BatchPayments\WeeklyAllowanceBatchPaymentSourceAdapter;
use App\Support\Finance\MoneyString;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 3 — Adapter contract: every adapter reports its source type, rejects the wrong model, and
 * emits the same normalized, versioned, IDR decimal-string snapshot shape.
 */
it('reports the correct source type for each adapter', function () {
    expect(app(WeeklyAllowanceBatchPaymentSourceAdapter::class)->sourceType())->toBe(PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH);
    expect(app(OvertimePayBatchPaymentSourceAdapter::class)->sourceType())->toBe(PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH);
    expect(app(LatePayCompensationBatchPaymentSourceAdapter::class)->sourceType())->toBe(PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH);
    expect(app(PayrunBatchPaymentSourceAdapter::class)->sourceType())->toBe(PaymentBundleItem::SOURCE_PAYRUN_BATCH);
});

it('rejects an unsupported / wrong-model source', function () {
    $adapter = app(WeeklyAllowanceBatchPaymentSourceAdapter::class);
    $wrong = F::overtime(F::employee()); // an OvertimePayBatch, not a WeeklyAllowanceBatch

    expect(fn () => $adapter->assertSupports($wrong))
        ->toThrow(RuntimeException::class, 'Unsupported source for weekly allowance adapter');
});

it('builds a versioned, normalized snapshot with IDR currency and decimal-string totals', function () {
    $adapter = app(WeeklyAllowanceBatchPaymentSourceAdapter::class);
    $snapshot = $adapter->buildSnapshots(F::weeklyAllowance(F::employee(), '125000.00'))->primary();

    expect($snapshot)->toHaveKeys([
        'snapshot_version', 'source_type', 'source_id', 'source_number', 'source_status',
        'currency', 'total_amount', 'payable_count', 'summary', 'payees', 'protected_payees', 'source_hash',
    ]);
    expect($snapshot['snapshot_version'])->toBe(1);
    expect($snapshot['currency'])->toBe('IDR');
    expect($snapshot['total_amount'])->toBe('125000.00');
    expect($snapshot['total_amount'])->toBeString();
    expect($snapshot['payees'][0]['currency'])->toBe('IDR');
    expect($snapshot['payees'][0]['amount'])->toBeString();
});

it('returns the source total as a decimal-string MoneyString', function () {
    $total = app(OvertimePayBatchPaymentSourceAdapter::class)->sourceTotal(F::overtime(F::employee(), '75000.00'));

    expect($total)->toBeInstanceOf(MoneyString::class);
    expect((string) $total)->toBe('75000.00');
});

it('blocks the payroll adapter as ineligible (Phase 9 gate)', function () {
    expect(fn () => app(PayrunBatchPaymentSourceAdapter::class)->assertEligible(F::payrun()))
        ->toThrow(RuntimeException::class, 'Payroll bundling is blocked');
});

it('normalizes the snapshot key shape identically across all batch adapters', function () {
    $employee = F::employee();

    $weekly = app(WeeklyAllowanceBatchPaymentSourceAdapter::class)->buildSnapshots(F::weeklyAllowance($employee, '100000.00'))->primary();
    $overtime = app(OvertimePayBatchPaymentSourceAdapter::class)->buildSnapshots(F::overtime($employee, '100000.00'))->primary();
    $latePay = app(LatePayCompensationBatchPaymentSourceAdapter::class)->buildSnapshots(F::latePay($employee, '100000.00'))->primary();

    expect(array_keys($overtime))->toBe(array_keys($weekly));
    expect(array_keys($latePay))->toBe(array_keys($weekly));
});
