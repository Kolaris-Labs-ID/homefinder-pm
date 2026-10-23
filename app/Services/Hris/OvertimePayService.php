<?php

namespace App\Services\Hris;

use App\Exceptions\Hris\OvertimeBatchLockedException;
use App\Exceptions\Hris\OvertimeRateCoverageException;
use App\Facades\Features;
use App\Features\Feature;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\MasterHoliday;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

            $rateGaps = [];

            // The ceilings are calendar-week quantities, so they must be measured over
            // whole ISO weeks and over already-paid sessions too — otherwise generating
            // two half-week batches hands out the 18h budget twice.
            [$isoStart, $isoEnd] = $this->isoWeekSpan($weekStart, $weekEnd);
            $holidays = $this->holidayDates($isoStart, $isoEnd);

            foreach ($byEmployee as $employeeId => $employeeLogs) {
                if (! $employeeId) {
                    continue;
                }

                $employee = $employeeLogs->first()->user->employee;

                // Resolve the rate per log against the structure effective on that
                // log's work date — the rate frozen at session start is a UI estimate
                // and must never be the payable basis (a backdated raise never reaches it).
                $structures = $this->compensationResolver->structuresAsOf($employee, $weekEnd);

                $totalPay = 0;
                $totalMinutes = 0;
                $breakdown = [];

                // Allocate over every approved session in the ISO weeks this batch
                // touches, paid ones included, then read off only this batch's logs.
                $payable = $this->allocatePayableMinutes(
                    $this->weekLogsFor($employee, $isoStart, $isoEnd),
                    $holidays
                );

                foreach ($employeeLogs as $log) {
                    // A structure that resolves to a zero rate is as unpayable as a
                    // missing one — both must fail loudly rather than pay Rp 0.
                    $rate = $this->hourlyRateForLog($structures, $log);

                    if ($rate <= 0) {
                        $rateGaps[] = $this->rateGap($employee, $log);

                        continue;
                    }

                    // Persist what was actually paid, so the log is an audit record
                    // of the batch rather than of the estimate shown at start.
                    $log->forceFill(['hourly_rate' => $rate])->save();

                    $payableMinutes = $payable[$log->id] ?? 0;

                    // A session the ceilings leave nothing for is deferred, not written
                    // off: leaving it unlinked keeps it unpaid() so a later batch can
                    // still pay it. ponytail: a session permanently squeezed out by a
                    // busy day resurfaces every run until HR trims or splits it.
                    if ($payableMinutes <= 0) {
                        continue;
                    }

                    $pay = $log->calculateEstimatedPay($payableMinutes);
                    $totalPay += $pay;
                    $totalMinutes += $payableMinutes;
                    $breakdown[] = [
                        'overtime_log_id' => $log->id,
                        'task_name' => $log->task_name,
                        'start_time' => $log->start_time->toDateTimeString(),
                        'end_time' => $log->end_time?->toDateTimeString(),
                        'duration_minutes' => $log->duration_minutes,
                        'payable_minutes' => $payableMinutes,
                        'excess_minutes' => max(0, (int) $log->duration_minutes - $payableMinutes),
                        'hourly_rate' => $rate,
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

                // Link OvertimeLogs to this payment to prevent double-counting.
                // Only sessions this batch actually paid — linking a zero-pay one
                // would settle it forever.
                OvertimeLog::whereIn('id', array_column($breakdown, 'overtime_log_id'))
                    ->update(['overtime_payment_id' => $payment->id]);
            }

            // Fail the whole batch rather than pay an unresolvable session at Rp 0.
            // Every gap is reported at once so HR fixes them in one pass.
            if (! empty($rateGaps)) {
                throw new OvertimeRateCoverageException($rateGaps);
            }

            // Append weekend and holiday attendance sessions to the batch
            $this->appendWeekendAttendanceSessions($batch, $weekStart, $weekEnd);

            // Recompute totals from all payments (including weekend sessions)
            $this->finalizeBatch($batch);

            return $batch->fresh();
        });
    }

    /**
     * Re-resolve overtime rates on an existing, unpaid batch against the salary
     * structure effective on each log's work date. Use after a backdated salary
     * change so a draft/rejected batch can be corrected in place, keeping its
     * identity and approval history.
     *
     * Weekend/holiday sessions are re-priced too, from the daily_allowance_rate
     * effective on their own work date — the same resolution
     * appendWeekendAttendanceSessions() uses at generation time — so a batch
     * that was already generated agrees with a delete-then-regenerate.
     */
    public function recalculateBatch(OvertimePayBatch $batch): OvertimePayBatch
    {
        if ($batch->isLocked()) {
            throw new OvertimeBatchLockedException($batch);
        }

        return DB::transaction(function () use ($batch) {
            $rateGaps = [];

            foreach ($batch->payments()->with('employee')->get() as $payment) {
                $employee = $payment->employee;

                if (! $employee) {
                    continue;
                }

                $structures = $this->compensationResolver->structuresAsOf($employee, $batch->week_end_date);

                $sessions = $payment->sessions_breakdown ?? [];
                $totalPay = 0.0;

                // Re-derive the ceilings from the live logs so a recalculation
                // agrees with a delete-then-regenerate, rather than trusting the
                // payable_minutes frozen into the stored breakdown.
                [$isoStart, $isoEnd] = $this->isoWeekSpan($batch->week_start_date, $batch->week_end_date);
                $logs = $this->weekLogsFor($employee, $isoStart, $isoEnd)->keyBy('id');

                // A deleted log still keeps the pay it was recorded with below, so it
                // has to keep reserving its budget too — otherwise its minutes are
                // handed to a surviving session and the week is paid twice.
                $ghosts = collect($sessions)
                    ->filter(fn ($s) => ! empty($s['overtime_log_id']) && ! $logs->has($s['overtime_log_id']))
                    ->map(fn ($s) => tap(new OvertimeLog([
                        'start_time' => $s['start_time'],
                        'duration_minutes' => $s['payable_minutes'] ?? $s['duration_minutes'] ?? 0,
                    ]), fn ($log) => $log->id = $s['overtime_log_id']));

                $payable = $this->allocatePayableMinutes(
                    $logs->values()->concat($ghosts),
                    $this->holidayDates($isoStart, $isoEnd)
                );

                foreach ($sessions as $index => $session) {
                    // appendWeekendAttendanceSessions() never sets overtime_log_id, so an
                    // empty one is a weekend/holiday session, not an orphaned log — those
                    // two cases must not collapse into the same branch.
                    if (empty($session['overtime_log_id'])) {
                        $workDate = Carbon::parse($session['date']);
                        $dailyRate = $this->compensationResolver->dailyRateFrom($structures, $workDate);

                        if ($dailyRate <= 0) {
                            $rateGaps[] = $this->weekendRateGap($employee, $session);

                            continue;
                        }

                        $sessions[$index]['pay'] = round($dailyRate, 2);
                        $totalPay += $dailyRate;

                        continue;
                    }

                    $log = $logs->get($session['overtime_log_id']);

                    // The id was set but the log row is gone — nothing left to
                    // re-resolve against, so carry the recorded amount forward.
                    if (! $log) {
                        $totalPay += (float) ($session['pay'] ?? 0);

                        continue;
                    }

                    $rate = $this->hourlyRateForLog($structures, $log);

                    if ($rate <= 0) {
                        $rateGaps[] = $this->rateGap($employee, $log);

                        continue;
                    }

                    $log->forceFill(['hourly_rate' => $rate])->save();

                    $payableMinutes = $payable[$log->id] ?? 0;
                    $pay = $log->calculateEstimatedPay($payableMinutes);
                    $sessions[$index]['hourly_rate'] = $rate;
                    $sessions[$index]['payable_minutes'] = $payableMinutes;
                    $sessions[$index]['excess_minutes'] = max(0, (int) $log->duration_minutes - $payableMinutes);
                    $sessions[$index]['pay'] = round($pay, 2);
                    $totalPay += $pay;
                }

                $payment->update([
                    'sessions_breakdown' => $sessions,
                    'total_amount' => round($totalPay, 2),
                ]);
            }

            if (! empty($rateGaps)) {
                throw new OvertimeRateCoverageException($rateGaps);
            }

            $batch->recalculateSummary();

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

    // THE authoritative payable-minutes allocation (id => minutes): PP 35/2021
    // art. 26's 4h/day and 18h/week ceilings, keyed by ISO week so an odd batch
    // window cannot reset or stretch them. Oldest session first. A session's day
    // is its start date, the same attribution hourlyRateForLog() uses.
    // Pass every session that should consume budget, already-paid ones included.
    private function allocatePayableMinutes(Collection $logs, Collection $holidays): array
    {
        // A company with the statutory caps off pays every approved minute.
        if (! Features::active(Feature::OvertimeStatutoryCaps)) {
            return $logs->mapWithKeys(fn (OvertimeLog $log) => [$log->id => (int) $log->duration_minutes])->all();
        }

        $perDayUsed = [];
        $perWeekUsed = [];
        $allocated = [];

        foreach ($logs->sortBy('start_time') as $log) {
            $day = $log->start_time->toDateString();
            $minutes = (int) $log->duration_minutes;

            // Rest-day and holiday work is exempt from both ceilings under the same
            // article — it has its own multiplier table — so it is neither trimmed
            // nor allowed to eat the weekday budget.
            if ($log->start_time->isWeekend() || $holidays->contains($day)) {
                $allocated[$log->id] = $minutes;

                continue;
            }

            $week = $log->start_time->format('o-W');

            $minutes = (int) min(
                $minutes,
                max(0, OvertimeLog::MAX_DAILY_PAYABLE_MINUTES - ($perDayUsed[$day] ?? 0)),
                max(0, OvertimeLog::MAX_WEEKLY_PAYABLE_MINUTES - ($perWeekUsed[$week] ?? 0)),
            );

            $allocated[$log->id] = $minutes;
            $perDayUsed[$day] = ($perDayUsed[$day] ?? 0) + $minutes;
            $perWeekUsed[$week] = ($perWeekUsed[$week] ?? 0) + $minutes;
        }

        return $allocated;
    }

    // Whole ISO weeks covering an arbitrary batch window.
    private function isoWeekSpan(Carbon $from, Carbon $to): array
    {
        return [
            $from->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
            $to->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
        ];
    }

    private function holidayDates(Carbon $from, Carbon $to): Collection
    {
        return MasterHoliday::active()
            ->forDateRange($from->toDateString(), $to->toDateString())
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());
    }

    // Paid ones included: the allocator needs settled hours to know what is left.
    private function weekLogsFor(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        return OvertimeLog::approved()
            ->where('user_id', $employee->user_id)
            ->whereBetween('start_time', [$from, $to])
            ->get();
    }

    /**
     * THE authoritative overtime rate. Every path — generation, --force
     * regeneration and recalculateBatch — resolves through here.
     *
     * basic_salary of the structure effective on the log's work date / 173.
     * Returns 0.0 when unresolvable (no structure, or one with no basic salary);
     * callers must treat that as a coverage gap, never as free work.
     */
    private function hourlyRateForLog(Collection $structures, OvertimeLog $log): float
    {
        $structure = $this->compensationResolver->resolveFrom(
            $structures,
            $log->start_time->copy()->startOfDay()
        );

        return $structure ? $structure->overtime_hourly_rate : 0.0;
    }

    /**
     * @return array{employee_id:int,employee_name:?string,work_date:string,overtime_log_id:int,attendance_log_id:null}
     */
    private function rateGap(Employee $employee, OvertimeLog $log): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->user?->name,
            'work_date' => $log->start_time->toDateString(),
            'overtime_log_id' => $log->id,
            'attendance_log_id' => null,
        ];
    }

    /**
     * Same as rateGap(), for a weekend/holiday attendance session instead of
     * an OvertimeLog — there is no log to report, only the attendance day.
     *
     * @return array{employee_id:int,employee_name:?string,work_date:string,overtime_log_id:null,attendance_log_id:?int}
     */
    private function weekendRateGap(Employee $employee, array $session): array
    {
        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->user?->name,
            'work_date' => $session['date'],
            'overtime_log_id' => null,
            'attendance_log_id' => $session['attendance_log_id'] ?? null,
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
