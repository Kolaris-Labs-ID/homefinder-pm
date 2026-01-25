<?php

use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\WeeklyAllowanceService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(WeeklyAllowanceService::class);
});

describe('WeeklyAllowance Workflow Transitions', function () {
    it('can submit draft to HR', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'draft',
        ]);

        $result = $this->service->submitToHr($batch);

        expect($result->status)->toBe('pending_hr');
    });

    it('throws exception when submitting non-draft to HR', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_finance',
        ]);

        expect(fn () => $this->service->submitToHr($batch))
            ->toThrow(\Exception::class);
    });

    it('can submit to finance from pending_hr', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_hr',
        ]);

        $result = $this->service->submitToFinance($batch, $this->user);

        expect($result->status)->toBe('pending_finance')
            ->and($result->hr_reviewed_by)->toBe($this->user->id)
            ->and($result->hr_reviewed_at)->not()->toBeNull();
    });

    it('throws exception when submitting with bank warnings', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_hr',
        ]);

        // Create employee and payment with bank warning
        $employee = Employee::factory()->create([
            'bank_name' => null,
            'bank_account_number' => null,
            'bank_account_holder' => null,
        ]);

        WeeklyAllowancePayment::create([
            'weekly_allowance_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'daily_rate' => 70000,
            'days_present' => 5,
            'total_amount' => 350000,
            'has_bank_warning' => true,
        ]);

        expect(fn () => $this->service->submitToFinance($batch, $this->user))
            ->toThrow(\Exception::class, 'missing bank information');
    });

    it('can approve by finance', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_finance',
        ]);

        // Create payment
        $employee = Employee::factory()->create();
        WeeklyAllowancePayment::create([
            'weekly_allowance_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'daily_rate' => 70000,
            'days_present' => 5,
            'total_amount' => 350000,
            'status' => 'pending',
        ]);

        $result = $this->service->approveByFinance($batch, $this->user);

        expect($result->status)->toBe('pending_final')
            ->and($result->finance_approved_by)->toBe($this->user->id);

        // Payment should be marked as approved
        $payment = $batch->payments()->first();
        expect($payment->status)->toBe('approved');
    });

    it('can give final approval', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_final',
        ]);

        $result = $this->service->approveFinal($batch, $this->user);

        expect($result->status)->toBe('approved')
            ->and($result->final_approved_by)->toBe($this->user->id);
    });

    it('can mark as paid', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'approved',
        ]);

        // Create approved payment
        $employee = Employee::factory()->create();
        WeeklyAllowancePayment::create([
            'weekly_allowance_batch_id' => $batch->id,
            'employee_id' => $employee->id,
            'daily_rate' => 70000,
            'days_present' => 5,
            'total_amount' => 350000,
            'status' => 'approved',
        ]);

        $result = $this->service->markAsPaid($batch, $this->user);

        expect($result->status)->toBe('paid')
            ->and($result->paid_by)->toBe($this->user->id)
            ->and($result->paid_at)->not()->toBeNull();

        // Payment should be marked as paid
        $payment = $batch->payments()->first();
        expect($payment->status)->toBe('paid');
    });

    it('can cancel batch', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'pending_finance',
        ]);

        $result = $this->service->cancelBatch($batch, 'Wrong week selected');

        expect($result->status)->toBe('cancelled')
            ->and($result->rejection_reason)->toBe('Wrong week selected');
    });

    it('cannot cancel paid batch', function () {
        $batch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2026-01-20',
            'week_end_date' => '2026-01-24',
            'payment_date' => '2026-01-28',
            'status' => 'paid',
        ]);

        expect(fn () => $this->service->cancelBatch($batch, 'Mistake'))
            ->toThrow(\Exception::class);
    });
});

describe('WeeklyAllowance Reconciliation', function () {
    it('calculates monthly paid total', function () {
        $employee = Employee::factory()->create();

        // Create 4 weeks of paid batches
        for ($week = 1; $week <= 4; $week++) {
            $weekStart = \Carbon\Carbon::parse("2026-01-" . (($week - 1) * 7 + 6));
            $weekEnd = $weekStart->copy()->addDays(4);

            $batch = WeeklyAllowanceBatch::create([
                'week_start_date' => $weekStart,
                'week_end_date' => $weekEnd,
                'payment_date' => $weekEnd->copy()->next(\Carbon\Carbon::TUESDAY),
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            WeeklyAllowancePayment::create([
                'weekly_allowance_batch_id' => $batch->id,
                'employee_id' => $employee->id,
                'daily_rate' => 70000,
                'days_present' => 5,
                'total_amount' => 350000,
                'status' => 'paid',
            ]);
        }

        $monthStart = \Carbon\Carbon::parse('2026-01-01');
        $monthEnd = \Carbon\Carbon::parse('2026-01-31');

        $total = $this->service->getMonthlyPaidTotal($employee, $monthStart, $monthEnd);

        expect($total)->toBe(1400000.0); // 4 weeks * 350000
    });
});
