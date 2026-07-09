<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\MasterHoliday;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendancePayrollService
{
    public function __construct(
        protected PayrollSettingService $settingService
    ) {}

    /**
     * Get attendance summary for an employee within a date range.
     */
    public function getAttendanceSummary(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->endOfDay();

        $logs = AttendanceLog::forEmployee($employee->id)
            ->forDateRange($periodStart->toDateString(), $periodEnd->toDateString())
            ->get();

        $presentDays = $logs->where('status', AttendanceLog::STATUS_PRESENT)->count();
        $lateDays = $logs->where('status', AttendanceLog::STATUS_LATE)->count();
        $halfDays = $logs->where('status', AttendanceLog::STATUS_HALF_DAY)->count();
        $absentDays = $logs->where('status', AttendanceLog::STATUS_ABSENT)->count();
        $leaveDays = $logs->where('status', AttendanceLog::STATUS_LEAVE)->count();
        $holidayDays = $logs->where('status', AttendanceLog::STATUS_HOLIDAY)->count();

        $wfhDays = $logs->where('mode', AttendanceLog::MODE_WFH)->count();
        $officeDays = $logs->where('mode', AttendanceLog::MODE_OFFICE)->count();
        $missingCheckoutDays = $logs->where('missing_checkout', true)->count();
        $visitDays = $logs->filter(function ($log) {
            return $log->source === AttendanceLog::SOURCE_VISIT
                || $log->status === AttendanceLog::STATUS_VISIT;
        })->pluck('date')->map(fn ($date) => Carbon::parse($date)->toDateString())->unique()->count();

        // Count weekend work days (Sat/Sun with actual attendance)
        $weekendWorkDays = $logs->filter(function ($log) {
            return in_array($log->status, [
                AttendanceLog::STATUS_PRESENT,
                AttendanceLog::STATUS_LATE,
            ])
                && Carbon::parse($log->date)->isWeekend();
        })->count();

        // Count holiday work days (weekday holiday logs where employee actually checked in)
        $holidayWorkDays = $logs->filter(function ($log) {
            return $log->status === AttendanceLog::STATUS_HOLIDAY
                && $log->check_in_time !== null
                && ! Carbon::parse($log->date)->isWeekend();
        })->count();

        $actualAttendanceDays = $this->getPayrollActualAttendanceDays($employee, $periodStart, $periodEnd);
        $eligiblePaidHolidayDays = $this->getPaidHolidaysForEmployeeInPeriod($employee, $periodStart, $periodEnd);
        $effectiveWorkingDays = $this->calculateEffectiveWorkingDays($periodStart, $periodEnd);

        // Classify leave days that protect basic salary vs unpaid leave days that
        // still reduce prorated salary. Driven by approved LeaveRequest date ranges,
        // NOT status='leave' attendance rows — an employee on approved leave usually
        // has no attendance row at all, and their salary must still be protected.
        [$paidLeaveDays, $unpaidLeaveDays] = $this->classifyLeaveDays(
            $employee,
            $periodStart,
            $periodEnd
        );

        return [
            'total_working_days' => $effectiveWorkingDays,
            'weekday_working_days' => $this->calculateWorkingDays($periodStart, $periodEnd),
            'effective_working_days' => $effectiveWorkingDays,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            // Raw status='leave' log count, retained for reporting only — unrelated
            // to the request-driven paid/unpaid split below.
            'leave_days' => $leaveDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'holiday_days' => $holidayDays,
            'eligible_paid_holiday_days' => $eligiblePaidHolidayDays,
            'wfh_days' => $wfhDays,
            'office_days' => $officeDays,
            'visit_days' => $visitDays,
            'missing_checkout_days' => $missingCheckoutDays,
            'actual_attendance_days' => $actualAttendanceDays,
            'total_attendance_days' => $presentDays + $lateDays + $halfDays + $visitDays,
            'total_paid_days' => $actualAttendanceDays + $eligiblePaidHolidayDays + $paidLeaveDays,
            // Weekend/holiday work — excluded from prorate, paid via overtime pay
            'weekend_work_days' => $weekendWorkDays,
            'holiday_work_days' => $holidayWorkDays,
        ];
    }

    /**
     * Split approved-leave days in the period into salary-protecting vs unpaid.
     *
     * Counts weekday, non-holiday dates covered by an approved LeaveRequest that
     * the employee did not actually attend (attended dates already count toward
     * actual_attendance_days — skipping them here prevents double counting).
     * Annual and sick leave protect the basic salary from prorate; any other
     * leave code still prorates it, and a date covered by no approved request
     * at all (alpha) is simply not counted.
     *
     * @return array{0:int,1:int} [paidLeaveDays, unpaidLeaveDays]
     */
    protected function classifyLeaveDays(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $approvedLeaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get();

        if ($approvedLeaves->isEmpty()) {
            return [0, 0];
        }

        $attendedDates = $this->getActualAttendanceDateStrings($employee->id, $periodStart, $periodEnd);
        $holidayDates = $this->getActiveWeekdayHolidayDateStrings($periodStart, $periodEnd);

        $paid = 0;
        $unpaid = 0;

        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $date) {
            $dateString = $date->toDateString();

            if ($date->isWeekend()
                || in_array($dateString, $holidayDates, true)
                || in_array($dateString, $attendedDates, true)
            ) {
                continue;
            }

            $coveringRequests = $approvedLeaves->filter(
                fn (LeaveRequest $lr) => $lr->start_date->lte($date) && $lr->end_date->gte($date)
            );

            if ($coveringRequests->isEmpty()) {
                continue;
            }

            // Overlapping approvals are possible (no overlap guard at submission) —
            // prefer a salary-protecting request so an unpaid overlap can't
            // non-deterministically strip pay protection.
            $covering = $coveringRequests->first(
                fn (LeaveRequest $lr) => in_array($lr->leaveType?->code, [LeaveBalanceService::CODE_ANNUAL, LeaveBalanceService::CODE_SICK], true)
            ) ?? $coveringRequests->first();

            if (in_array($covering->leaveType?->code, [LeaveBalanceService::CODE_ANNUAL, LeaveBalanceService::CODE_SICK], true)) {
                $paid++;
            } else {
                $unpaid++;
            }
        }

        return [$paid, $unpaid];
    }

    /**
     * Calculate working days in a period (excluding weekends).
     */
    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $workingDays = 0;

        foreach ($period as $date) {
            // Exclude weekends (Saturday = 6, Sunday = 0)
            if (! $date->isWeekend()) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Return effective working days for a calendar month.
     */
    public function getEffectiveWorkingDays(int $month, int $year): int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->calculateEffectiveWorkingDays($startDate, $endDate);
    }

    /**
     * Return active weekday holidays an employee is entitled to in a month.
     */
    public function getPaidHolidaysForEmployee(int $employeeId, int $month, int $year): int
    {
        $employee = Employee::find($employeeId);

        if (! $employee) {
            return 0;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->getPaidHolidaysForEmployeeInPeriod($employee, $startDate, $endDate);
    }

    /**
     * Count distinct days in a month where the employee clocked in or made a visit.
     */
    public function getTotalActualAttendance(int $employeeId, int $month, int $year): int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->getActualAttendanceDaysInPeriod($employeeId, $startDate, $endDate);
    }

    protected function calculateEffectiveWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        return max(0, $this->calculateWorkingDays($startDate, $endDate) - $this->countActiveWeekdayHolidays($startDate, $endDate));
    }

    protected function countActiveWeekdayHolidays(Carbon $startDate, Carbon $endDate): int
    {
        return count($this->getActiveWeekdayHolidayDateStrings($startDate, $endDate));
    }

    /**
     * Active weekday holiday dates in the period as 'Y-m-d' strings.
     */
    protected function getActiveWeekdayHolidayDateStrings(Carbon $startDate, Carbon $endDate): array
    {
        return MasterHoliday::active()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->get()
            ->filter(fn (MasterHoliday $holiday) => ! $holiday->date->isWeekend())
            ->map(fn (MasterHoliday $holiday) => $holiday->date->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    protected function getPaidHolidaysForEmployeeInPeriod(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        if (! $employee->is_active) {
            return 0;
        }

        $hireDate = $employee->hire_date?->copy()->startOfDay();
        $endEmploymentDate = $employee->employment_end_date?->copy()->startOfDay();

        return MasterHoliday::active()
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->get()
            ->filter(function (MasterHoliday $holiday) use ($hireDate, $endEmploymentDate) {
                $holidayDate = $holiday->date->copy()->startOfDay();

                if ($holidayDate->isWeekend()) {
                    return false;
                }

                if ($hireDate && $hireDate->gt($holidayDate)) {
                    return false;
                }

                if ($endEmploymentDate && $endEmploymentDate->lt($holidayDate)) {
                    return false;
                }

                return true;
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->count();
    }

    protected function getPayrollActualAttendanceDays(Employee $employee, Carbon $startDate, Carbon $endDate): int
    {
        $holidayDates = $this->getActiveWeekdayHolidayDateStrings($startDate, $endDate);

        $dates = collect($this->getActualAttendanceDateStrings($employee->id, $startDate, $endDate));

        return $dates
            ->filter(function (string $date) use ($holidayDates) {
                $carbonDate = Carbon::parse($date);

                return ! $carbonDate->isWeekend()
                    && ! in_array($date, $holidayDates, true);
            })
            ->unique()
            ->count();
    }

    protected function getActualAttendanceDaysInPeriod(int $employeeId, Carbon $startDate, Carbon $endDate): int
    {
        return count($this->getActualAttendanceDateStrings($employeeId, $startDate, $endDate));
    }

    protected function getActualAttendanceDateStrings(int $employeeId, Carbon $startDate, Carbon $endDate): array
    {
        $attendanceDates = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->forDateRange($startDate->toDateString(), $endDate->toDateString())
            ->where(function ($query) {
                // Imported/manual present records may not have a check-in timestamp.
                $query->whereNotNull('check_in_time')
                    ->orWhere('source', AttendanceLog::SOURCE_VISIT)
                    ->orWhere('status', AttendanceLog::STATUS_VISIT)
                    ->orWhereIn('status', [
                        AttendanceLog::STATUS_PRESENT,
                        AttendanceLog::STATUS_LATE,
                        AttendanceLog::STATUS_HALF_DAY,
                    ]);
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        $visitDates = ClientVisit::query()
            ->where('employee_id', $employeeId)
            ->whereDate('visit_date', '>=', $startDate->toDateString())
            ->whereDate('visit_date', '<=', $endDate->toDateString())
            ->where(function ($query) {
                $query->whereNotNull('actual_check_in')
                    ->orWhereIn('status', [
                        ClientVisit::STATUS_IN_PROGRESS,
                        ClientVisit::STATUS_COMPLETED,
                    ]);
            })
            ->pluck('visit_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString());

        return $attendanceDates
            ->merge($visitDates)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Calculate actual days worked (countable for payroll).
     *
     * Paid leave days (approved leave covered by a quota-consuming leave type)
     * are treated as worked so they do NOT prorate the basic salary. Unpaid
     * leave days and unexcused absences still reduce actual days worked.
     *
     * @param  array  $config  Configuration for what to count
     */
    public function calculateActualDaysWorked(array $attendanceSummary, array $config = []): float
    {
        $countLate = $config['count_late'] ?? true;
        $halfDayRatio = $config['half_day_ratio'] ?? 0.5;
        $countPaidLeave = $config['count_paid_leave'] ?? true;

        $days = $attendanceSummary['actual_attendance_days'] ?? $attendanceSummary['present_days'];

        if (! array_key_exists('actual_attendance_days', $attendanceSummary) && $countLate) {
            $days += $attendanceSummary['late_days'];
        }

        // Half days count as configured ratio
        if (! array_key_exists('actual_attendance_days', $attendanceSummary)) {
            $days += $attendanceSummary['half_days'] * $halfDayRatio;
        }

        // Paid leave (quota-consuming) protects basic salary from prorate.
        if ($countPaidLeave) {
            $days += $attendanceSummary['paid_leave_days'] ?? 0;
        }

        $days += $attendanceSummary['eligible_paid_holiday_days'] ?? 0;

        if (! array_key_exists('actual_attendance_days', $attendanceSummary)) {
            // Exclude weekend and holiday work days — those are paid via overtime, not prorate
            $days -= ($attendanceSummary['weekend_work_days'] ?? 0);
        }

        return max(0.0, $days);
    }

    /**
     * Calculate prorate ratio.
     */
    public function calculateProrateRatio(int $actualDaysWorked, int $totalWorkingDays): float
    {
        if ($totalWorkingDays <= 0) {
            return 1.0;
        }

        $ratio = $actualDaysWorked / $totalWorkingDays;

        // Cap at 1.0 (100%)
        return min($ratio, 1.0);
    }

    /**
     * Calculate daily allowance based on attendance.
     *
     * @param  array  $allowanceConfig  Allowance configuration
     * @param  array  $attendanceSummary  Attendance summary
     */
    public function calculateDailyAllowance(array $allowanceConfig, array $attendanceSummary): float
    {
        // If not attendance-based, return fixed amount
        $type = $allowanceConfig['type'] ?? 'fixed';
        if ($type !== 'attendance_based') {
            return (float) ($allowanceConfig['amount'] ?? 0);
        }

        $perDayAmount = (float) ($allowanceConfig['per_day_amount'] ?? 0);
        if ($perDayAmount <= 0) {
            return 0;
        }

        // Calculate countable days
        $countableDays = $attendanceSummary['present_days'];

        // Add late days if configured
        if ($allowanceConfig['count_late'] ?? true) {
            $countableDays += $attendanceSummary['late_days'];
        }

        // Add half days with ratio
        $halfDayRatio = (float) ($allowanceConfig['half_day_calculation'] ?? 0.5);
        $countableDays += $attendanceSummary['half_days'] * $halfDayRatio;

        // Subtract WFH days if not counted
        if (! ($allowanceConfig['count_wfh'] ?? true)) {
            $countableDays -= $attendanceSummary['wfh_days'];
        }

        // Ensure non-negative
        $countableDays = max(0, $countableDays);

        // Calculate total
        $amount = $countableDays * $perDayAmount;

        // Apply maximum cap if set
        $maxAmount = (float) ($allowanceConfig['max_amount'] ?? 0);
        if ($maxAmount > 0) {
            $amount = min($amount, $maxAmount);
        }

        return round($amount, 2);
    }

    /**
     * Calculate all variable allowances based on attendance.
     *
     * @param  array  $variableAllowances  Array of allowance configurations
     * @param  array  $attendanceSummary  Attendance summary
     * @return array ['total' => float, 'breakdown' => array]
     */
    public function calculateVariableAllowances(array $variableAllowances, array $attendanceSummary): array
    {
        $breakdown = [];
        $total = 0;

        foreach ($variableAllowances as $allowance) {
            $amount = $this->calculateDailyAllowance($allowance, $attendanceSummary);
            $breakdown[] = [
                'name' => $allowance['name'] ?? 'Unknown',
                'type' => $allowance['type'] ?? 'fixed',
                'calculated_amount' => $amount,
                'config' => $allowance,
            ];
            $total += $amount;
        }

        return [
            'total' => round($total, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate prorated basic salary.
     */
    public function calculateProratedSalary(float $fullBasicSalary, float $prorateRatio): float
    {
        return round($fullBasicSalary * $prorateRatio, 2);
    }

    /**
     * Get attendance summary formatted for payslip display.
     */
    public function getAttendanceLabel(array $attendanceSummary): string
    {
        $actual = $attendanceSummary['present_days'] + $attendanceSummary['late_days'];
        $total = $attendanceSummary['total_working_days'];

        return "{$actual}/{$total} HK";
    }
}
