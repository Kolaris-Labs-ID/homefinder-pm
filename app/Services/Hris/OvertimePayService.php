<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
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
    protected CompensationResolver $compensationResolver;

    public function __construct(?CompensationResolver $compensationResolver = null)
    {
        $this->compensationResolver = $compensationResolver ?? app(CompensationResolver::class);
    }

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
                'week_end_date' => $weekEnd,
                'payment_date' => $paymentDate,
                'status' => OvertimePayBatch::STATUS_DRAFT,
                'generated_by' => $generatedBy?->id ?? Auth::id(),
                'generated_at' => now(),
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

            foreach ($byEmployee as $employeeId => $employeeLogs) {
                if (! $employeeId) {
                    continue;
                }

                $employee = $employeeLogs->first()->user->employee;

                $totalPay = 0;
                $totalMinutes = 0;
                $breakdown = [];

                foreach ($employeeLogs as $log) {
                    $pay = $log->calculateEstimatedPay();
                    $totalPay += $pay;
                    $totalMinutes += $log->duration_minutes;
                    $breakdown[] = [
                        'overtime_log_id' => $log->id,
                        'task_name' => $log->task_name,
                        'start_time' => $log->start_time->toDateTimeString(),
                        'end_time' => $log->end_time?->toDateTimeString(),
                        'duration_minutes' => $log->duration_minutes,
                        'hourly_rate' => (float) $log->hourly_rate,
                        'pay' => round($pay, 2),
                    ];
                }

                $hasBankWarning = empty($employee->bank_name)
                    || empty($employee->bank_account_number)
                    || empty($employee->bank_account_holder);

                $payment = OvertimePayment::create([
                    'overtime_pay_batch_id' => $batch->id,
                    'employee_id' => $employeeId,
                    'total_sessions' => count($breakdown),
                    'total_duration_minutes' => $totalMinutes,
                    'total_amount' => round($totalPay, 2),
                    'sessions_breakdown' => $breakdown,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'bank_account_holder' => $employee->bank_account_holder,
                    'has_bank_warning' => $hasBankWarning,
                    'status' => OvertimePayment::STATUS_PENDING,
                ]);

                // Link OvertimeLogs to this payment to prevent double-counting
                OvertimeLog::whereIn('id', $employeeLogs->pluck('id'))
                    ->update(['overtime_payment_id' => $payment->id]);
            }

            // Append weekend and holiday attendance sessions to the batch
            $this->appendWeekendAttendanceSessions($batch, $weekStart, $weekEnd);

            // Recompute totals from all payments (including weekend sessions)
            $this->finalizeBatch($batch);

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
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        // Payment date is Tuesday of the following week
        $payment = $end->copy()->next(Carbon::TUESDAY);

        return [
            'start' => $start,
            'end' => $end,
            'payment' => $payment,
        ];
    }

    /**
     * Recompute and persist batch-level totals from all child payments.
     * Extracted so it can be called after weekend sessions are appended.
     */
    private function finalizeBatch(OvertimePayBatch $batch): void
    {
        $payments = $batch->payments()->get();

        $totalAmount = $payments->sum(fn ($p) => (float) $p->total_amount);
        $warningCount = $payments->where('has_bank_warning', true)->count();

        $batch->update([
            'total_employees' => $payments->count(),
            'employees_with_warnings' => $warningCount,
            'total_amount' => round($totalAmount, 2),
            'status' => OvertimePayBatch::STATUS_CALCULATED,
        ]);
    }

    /**
     * For each active employee, find weekend (Sat/Sun) and weekday-holiday
     * AttendanceLogs in the given week and append them as virtual sessions
     * to the employee's OvertimePayment for this batch.
     *
     * Rules:
     * - Qualifying statuses: present, late, or half-day on Sat/Sun; OR status=holiday
     *   (with check_in) on a weekday.
     * - Amount per qualifying day = salary_structure.daily_allowance_rate (full, no penalty).
     * - Weekday holiday logs without a check-in are excluded.
     * - Idempotent: skips sessions whose attendance_log_id is already recorded.
     */
    private function appendWeekendAttendanceSessions(
        OvertimePayBatch $batch,
        Carbon $weekStart,
        Carbon $weekEnd
    ): void {
        $employees = Employee::active()->get();

        foreach ($employees as $employee) {
            // Fetch all logs for this employee in the week
            $allLogs = AttendanceLog::forEmployee($employee->id)
                ->forDateRange($weekStart, $weekEnd)
                ->get();

            // Filter to weekend-work and weekday-holiday-work logs
            $qualifyingLogs = $allLogs->filter(function (AttendanceLog $log) {
                $date = Carbon::parse($log->date);
                $isWeekend = $date->isWeekend();

                // Any weekend attendance earns the full daily allowance regardless of
                // hours — present, late, or half-day all qualify (half-day covers
                // legacy/imported rows; the live flow now records weekend work as present).
                $isWeekendWork = $isWeekend
                    && in_array($log->status, [
                        AttendanceLog::STATUS_PRESENT,
                        AttendanceLog::STATUS_LATE,
                        AttendanceLog::STATUS_HALF_DAY,
                    ]);

                $isHolidayWork = ! $isWeekend
                    && $log->status === AttendanceLog::STATUS_HOLIDAY
                    && $log->check_in_time !== null;

                return $isWeekendWork || $isHolidayWork;
            });

            if ($qualifyingLogs->isEmpty()) {
                continue;
            }

            // Load all structures effective on/before week end once; the daily rate
            // for each qualifying day is resolved by that day's date so a rate change
            // mid-week only applies from its effective_date onward.
            $salaryStructures = $this->compensationResolver->structuresAsOf($employee, $weekEnd);

            if ($salaryStructures->isEmpty()) {
                continue;
            }

            // Find existing OvertimePayment for this employee in the batch
            $payment = $batch->payments()->where('employee_id', $employee->id)->first();

            // Collect attendance_log_ids already in existing sessions (idempotency guard)
            $existingLogIds = [];
            if ($payment) {
                foreach ($payment->sessions_breakdown ?? [] as $session) {
                    if (isset($session['attendance_log_id'])) {
                        $existingLogIds[] = (int) $session['attendance_log_id'];
                    }
                }
            }

            $newSessions = [];
            $addedAmount = 0.0;

            foreach ($qualifyingLogs as $log) {
                if (in_array($log->id, $existingLogIds)) {
                    continue; // Already recorded — skip to keep idempotent
                }

                // Rate effective on the day actually worked (0 if no structure covers it yet).
                $logDate = Carbon::parse($log->date);
                $dailyRate = $this->compensationResolver->dailyRateFrom($salaryStructures, $logDate);
                if ($dailyRate <= 0) {
                    continue;
                }

                $isHoliday = $log->status === AttendanceLog::STATUS_HOLIDAY;
                $newSessions[] = [
                    'overtime_log_id' => null,
                    'attendance_log_id' => $log->id,
                    'task_name' => $isHoliday ? 'Holiday attendance' : 'Weekend attendance',
                    'session_type' => $isHoliday ? 'holiday_attendance' : 'weekend_attendance',
                    'date' => $logDate->toDateString(),
                    'duration_minutes' => 0,
                    'hourly_rate' => 0.0,
                    'pay' => round($dailyRate, 2),
                ];
                $addedAmount += $dailyRate;
            }

            if (empty($newSessions)) {
                continue;
            }

            if ($payment) {
                // Merge new sessions into the existing payment
                $payment->update([
                    'sessions_breakdown' => array_merge($payment->sessions_breakdown ?? [], $newSessions),
                    'total_sessions' => $payment->total_sessions + count($newSessions),
                    'total_amount' => round((float) $payment->total_amount + $addedAmount, 2),
                ]);
            } else {
                // Create a new payment row for this employee (no OvertimeLog-based sessions)
                $hasBankWarning = empty($employee->bank_name)
                    || empty($employee->bank_account_number)
                    || empty($employee->bank_account_holder);

                OvertimePayment::create([
                    'overtime_pay_batch_id' => $batch->id,
                    'employee_id' => $employee->id,
                    'total_sessions' => count($newSessions),
                    'total_duration_minutes' => 0,
                    'total_amount' => round($addedAmount, 2),
                    'sessions_breakdown' => $newSessions,
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'bank_account_holder' => $employee->bank_account_holder,
                    'has_bank_warning' => $hasBankWarning,
                    'status' => OvertimePayment::STATUS_PENDING,
                ]);
            }
        }
    }
}
