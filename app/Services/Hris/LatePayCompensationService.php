<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
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

                $hasBankWarning = empty($employee->bank_name)
                    || empty($employee->bank_account_number)
                    || empty($employee->bank_account_holder);

                LatePayCompensationItem::create([
                    'late_pay_compensation_batch_id' => $batch->id,
                    'employee_id' => $employee->id,
                    'basic_salary_snapshot' => $basic,
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
}
