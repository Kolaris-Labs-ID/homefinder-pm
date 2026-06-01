<?php

use App\Models\Hris\Employee;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Services\Hris\WeeklyAllowanceService;

beforeEach(function () {
    $this->service = app(WeeklyAllowanceService::class);
});

// Batch approval transitions run through the unified PayrollApprovalService
// (covered by the PayrunBatch + PayrollApprovalWorkflow tests). The bespoke
// per-batch transition methods were retired; this file covers the
// weekly-allowance-specific reconciliation logic.

describe('WeeklyAllowance Reconciliation', function () {
    it('calculates monthly paid total', function () {
        $employee = Employee::factory()->create();

        // Create 4 weeks of paid batches
        for ($week = 1; $week <= 4; $week++) {
            $weekStart = \Carbon\Carbon::parse('2026-01-' . (($week - 1) * 7 + 6));
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
