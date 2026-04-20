<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\PayrollRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LatePayCompensationService
{
    public function __construct(
        protected PayrollCalculationService $payrollCalculationService
    ) {}

    /**
     * Calendar days late: actual_pay_date must be after scheduled_pay_date.
     */
    public static function calculateDaysLate(Carbon $scheduledPayDate, Carbon $actualPayDate): int
    {
        $scheduled = $scheduledPayDate->copy()->startOfDay();
        $actual = $actualPayDate->copy()->startOfDay();

        if ($actual->lte($scheduled)) {
            return 0;
        }

        return (int) $scheduled->diffInDays($actual);
    }

    /**
     * @throws \Exception
     */
    public function generateBatch(
        Carbon $scheduledPayDate,
        Carbon $actualPayDate,
        ?Carbon $compensationTransferDate = null,
        float $ratePercent = 1.0,
        ?User $generatedBy = null
    ): LatePayCompensationBatch {
        return DB::transaction(function () use (
            $scheduledPayDate,
            $actualPayDate,
            $compensationTransferDate,
            $ratePercent,
            $generatedBy
        ) {
            $scheduled = $scheduledPayDate->copy()->startOfDay();
            $actual = $actualPayDate->copy()->startOfDay();

            $existing = LatePayCompensationBatch::query()
                ->whereDate('scheduled_pay_date', $scheduled)
                ->whereDate('actual_pay_date', $actual)
                ->first();

            if ($existing) {
                throw new \Exception(
                    'A late pay compensation batch already exists for this scheduled and actual pay date pair.'
                );
            }

            $daysLate = self::calculateDaysLate($scheduled, $actual);

            if ($daysLate === 0) {
                throw new \Exception(
                    'No compensation due: actual pay date must be after scheduled pay date (calendar days late > 0).'
                );
            }

            $batch = LatePayCompensationBatch::create([
                'scheduled_pay_date' => $scheduled,
                'actual_pay_date' => $actual,
                'compensation_transfer_date' => $compensationTransferDate,
                'days_late' => $daysLate,
                'rate_percent' => round($ratePercent, 2),
                'status' => LatePayCompensationBatch::STATUS_DRAFT,
                'generated_by' => $generatedBy?->id ?? Auth::id(),
                'generated_at' => now(),
            ]);

            $employees = Employee::active()->get();
            $totalAmount = 0;
            $warningCount = 0;

            foreach ($employees as $employee) {
                $structure = $this->payrollCalculationService->getActiveSalaryStructure($employee, $actual);

                if (! $structure) {
                    continue;
                }

                $basic = (float) $structure->basic_salary;
                $amount = round($basic * ($ratePercent / 100.0) * $daysLate, 2);

                if ($amount <= 0) {
                    continue;
                }

                $thp = PayrollRecord::whereHas('payrunBatch', function ($q) use ($scheduled) {
                    $q->whereDate('pay_date', $scheduled);
                })
                    ->where('employee_id', $employee->id)
                    ->value('net_income');

                $hasBankWarning = empty($employee->bank_name)
                    || empty($employee->bank_account_number)
                    || empty($employee->bank_account_holder);

                LatePayCompensationItem::create([
                    'late_pay_compensation_batch_id' => $batch->id,
                    'employee_id' => $employee->id,
                    'basic_salary_snapshot' => $basic,
                    'thp_snapshot' => $thp ? round((float) $thp, 2) : null,
                    'days_late' => $daysLate,
                    'rate_percent' => round($ratePercent, 2),
                    'total_amount' => $amount,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'bank_account_holder' => $employee->bank_account_holder,
                    'has_bank_warning' => $hasBankWarning,
                    'status' => LatePayCompensationItem::STATUS_PENDING,
                ]);

                $totalAmount += $amount;
                if ($hasBankWarning) {
                    $warningCount++;
                }
            }

            if ($batch->items()->count() === 0) {
                $batch->delete();
                throw new \Exception(
                    'No employees with an active salary structure on the actual pay date; batch was not created.'
                );
            }

            $batch->update([
                'total_employees' => $batch->items()->count(),
                'employees_with_warnings' => $warningCount,
                'total_amount' => $totalAmount,
                'status' => LatePayCompensationBatch::STATUS_CALCULATED,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Compute the compensation amount for a single item, accounting for partial payments.
     *
     * Phase 1 (before the first partial payment): rate × basic_salary × days.
     * Phase 2+ (after the first partial payment): rate × remaining_thp × days,
     * where remaining_thp = thp_snapshot × (1 − cumulative_paid_percent / 100).
     * Falls back to basic_salary as the phase 2+ base when thp_snapshot is null or zero.
     */
    public static function computeItemAmount(
        float $basicSalary,
        float $ratePercent,
        Carbon $scheduledPayDate,
        Carbon $actualPayDate,
        iterable $partialPayments = [],
        ?float $thpSnapshot = null
    ): float {
        $scheduled = $scheduledPayDate->copy()->startOfDay();
        $actual = $actualPayDate->copy()->startOfDay();
        $rate = $ratePercent / 100.0;

        // Collect and sort partial payments by date
        $partials = collect($partialPayments)
            ->sortBy('payment_date')
            ->values();

        if ($partials->isEmpty()) {
            $daysLate = (int) $scheduled->diffInDays($actual);

            return round($basicSalary * $rate * $daysLate, 2);
        }

        // For phase 2+ the base is THP; fall back to basic_salary when unavailable.
        $thpBase = ($thpSnapshot !== null && $thpSnapshot > 0) ? $thpSnapshot : $basicSalary;

        $totalAmount = 0.0;
        $paidPercent = 0.0;
        $phaseStart = $scheduled->copy();
        $isFirstPhase = true;

        foreach ($partials as $partial) {
            $paymentDate = Carbon::parse($partial->payment_date)->startOfDay();

            // Phase: from phaseStart to this partial payment date
            $phaseDays = (int) $phaseStart->diffInDays($paymentDate);

            if ($phaseDays > 0) {
                if ($isFirstPhase) {
                    // Phase 1: penalty on full basic salary (no paid percent yet)
                    $totalAmount += $basicSalary * $rate * $phaseDays;
                } else {
                    // Phase 2+: penalty on remaining THP
                    $remainingFraction = 1.0 - ($paidPercent / 100.0);
                    $totalAmount += $thpBase * $remainingFraction * $rate * $phaseDays;
                }
            }

            $paidPercent += (float) $partial->paid_percent;
            $phaseStart = $paymentDate->copy();
            $isFirstPhase = false;
        }

        // Final phase: from last partial payment to actual pay date
        $finalDays = (int) $phaseStart->diffInDays($actual);

        if ($finalDays > 0) {
            if ($isFirstPhase) {
                // No partials were processed (all had 0 days before them); treat as phase 1
                $totalAmount += $basicSalary * $rate * $finalDays;
            } else {
                // Phase 2+: penalty on remaining THP
                $remainingFraction = 1.0 - ($paidPercent / 100.0);
                $totalAmount += $thpBase * $remainingFraction * $rate * $finalDays;
            }
        }

        return round($totalAmount, 2);
    }

    /**
     * Recalculate a single item's total_amount based on its partial payments.
     */
    public function recalculateItem(LatePayCompensationItem $item): LatePayCompensationItem
    {
        $batch = $item->batch;

        $amount = self::computeItemAmount(
            (float) $item->basic_salary_snapshot,
            (float) $item->rate_percent,
            $batch->scheduled_pay_date,
            $batch->actual_pay_date,
            $item->partialPayments()->orderBy('payment_date')->get(),
            $item->thp_snapshot !== null ? (float) $item->thp_snapshot : null
        );

        $item->update(['total_amount' => $amount]);

        return $item->fresh();
    }

    /**
     * Recalculate all items in a batch and update batch totals.
     */
    public function recalculateBatch(LatePayCompensationBatch $batch): LatePayCompensationBatch
    {
        foreach ($batch->items as $item) {
            $this->recalculateItem($item);
        }

        $batch->recalculateSummary();

        return $batch->fresh();
    }
}
