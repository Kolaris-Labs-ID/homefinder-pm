<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OvertimePayService
{
    /**
     * Generate a weekly overtime pay batch for the given week.
     * Picks up all approved, unpaid OvertimeLogs whose start_time falls within the week.
     */
    public function generateBatch(
        Carbon $weekStart,
        Carbon $weekEnd,
        Carbon $paymentDate,
        ?User $generatedBy = null
    ): OvertimePayBatch {
        return DB::transaction(function () use ($weekStart, $weekEnd, $paymentDate, $generatedBy) {
            $existingBatch = OvertimePayBatch::forWeek($weekStart)->first();
            if ($existingBatch) {
                throw new \Exception("Overtime pay batch already exists for week {$weekStart->format('d M Y')}");
            }

            $batch = OvertimePayBatch::create([
                'week_start_date' => $weekStart,
                'week_end_date'   => $weekEnd,
                'payment_date'    => $paymentDate,
                'status'          => OvertimePayBatch::STATUS_DRAFT,
                'generated_by'    => $generatedBy?->id ?? Auth::id(),
                'generated_at'    => now(),
            ]);

            // Fetch all approved + unpaid logs for the week, grouped by user_id
            $logs = OvertimeLog::approved()
                ->unpaid()
                ->whereBetween('start_time', [
                    $weekStart->copy()->startOfDay(),
                    $weekEnd->copy()->endOfDay(),
                ])
                ->with('user.employee')
                ->get();

            $byEmployee = $logs->groupBy(fn (OvertimeLog $log) => $log->user->employee?->id);

            $totalAmount   = 0;
            $warningCount  = 0;

            foreach ($byEmployee as $employeeId => $employeeLogs) {
                if (!$employeeId) {
                    continue;
                }

                $employee = $employeeLogs->first()->user->employee;

                $totalPay      = 0;
                $totalMinutes  = 0;
                $breakdown     = [];

                foreach ($employeeLogs as $log) {
                    $pay = $log->calculateEstimatedPay();
                    $totalPay     += $pay;
                    $totalMinutes += $log->duration_minutes;
                    $breakdown[]   = [
                        'overtime_log_id'  => $log->id,
                        'task_name'        => $log->task_name,
                        'start_time'       => $log->start_time->toDateTimeString(),
                        'end_time'         => $log->end_time?->toDateTimeString(),
                        'duration_minutes' => $log->duration_minutes,
                        'hourly_rate'      => (float) $log->hourly_rate,
                        'pay'              => round($pay, 2),
                    ];
                }

                $hasBankWarning = empty($employee->bank_name)
                    || empty($employee->bank_account_number)
                    || empty($employee->bank_account_holder);

                $payment = OvertimePayment::create([
                    'overtime_pay_batch_id'   => $batch->id,
                    'employee_id'             => $employeeId,
                    'total_sessions'          => count($breakdown),
                    'total_duration_minutes'  => $totalMinutes,
                    'total_amount'            => round($totalPay, 2),
                    'sessions_breakdown'      => $breakdown,
                    'bank_name'               => $employee->bank_name,
                    'bank_account_number'     => $employee->bank_account_number,
                    'bank_account_holder'     => $employee->bank_account_holder,
                    'has_bank_warning'        => $hasBankWarning,
                    'status'                  => OvertimePayment::STATUS_PENDING,
                ]);

                // Link OvertimeLogs to this payment to prevent double-counting
                OvertimeLog::whereIn('id', $employeeLogs->pluck('id'))
                    ->update(['overtime_payment_id' => $payment->id]);

                $totalAmount += round($totalPay, 2);
                if ($hasBankWarning) {
                    $warningCount++;
                }
            }

            $batch->update([
                'total_employees'          => $batch->payments()->count(),
                'employees_with_warnings'  => $warningCount,
                'total_amount'             => $totalAmount,
                'status'                   => OvertimePayBatch::STATUS_CALCULATED,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Get total overtime pay (from paid batches) for an employee within a payroll period.
     * Used by PayrollCalculationService to populate total_overtime_pay on PayrollRecord.
     */
    public function getMonthlyPaidTotal(Employee $employee, Carbon $monthStart, Carbon $monthEnd): float
    {
        return (float) OvertimePayment::forEmployee($employee->id)
            ->where('status', OvertimePayment::STATUS_PAID)
            ->whereHas('batch', function ($query) use ($monthStart, $monthEnd) {
                $query->where('status', OvertimePayBatch::STATUS_PAID)
                    ->whereBetween('week_start_date', [$monthStart, $monthEnd]);
            })
            ->sum('total_amount');
    }

    /**
     * Get individual overtime payments for an employee in a payroll period.
     */
    public function getMonthlyPayments(Employee $employee, Carbon $monthStart, Carbon $monthEnd)
    {
        return OvertimePayment::forEmployee($employee->id)
            ->whereHas('batch', function ($query) use ($monthStart, $monthEnd) {
                $query->whereBetween('week_start_date', [$monthStart, $monthEnd]);
            })
            ->with('batch')
            ->get();
    }

    /**
     * Calculate the previous week's Monday and Sunday dates (full week for overtime).
     */
    public static function getPreviousWeekDates(?Carbon $fromDate = null): array
    {
        $from = $fromDate ?? Carbon::now();

        $start = $from->copy()->startOfWeek(Carbon::MONDAY)->subWeek();
        $end   = $start->copy()->endOfWeek(Carbon::SUNDAY);

        // Payment date is Tuesday of the following week
        $payment = $end->copy()->next(Carbon::TUESDAY);

        return [
            'start'   => $start,
            'end'     => $end,
            'payment' => $payment,
        ];
    }
}
