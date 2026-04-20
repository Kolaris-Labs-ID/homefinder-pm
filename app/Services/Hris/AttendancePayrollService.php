<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendancePayrollService
{
    public function __construct(
        protected PayrollSettingService $settingService
    ) {}

    /**
     * Get attendance summary for an employee within a date range.
     *
     * @param Employee $employee
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return array
     */
    public function getAttendanceSummary(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $logs = AttendanceLog::forEmployee($employee->id)
            ->forDateRange($periodStart, $periodEnd)
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

        // Classify leave days as paid (quota-consuming) vs unpaid so that
        // paid leave can protect the employee's basic salary from prorate.
        [$paidLeaveDays, $unpaidLeaveDays] = $this->classifyLeaveDays(
            $employee,
            $logs->where('status', AttendanceLog::STATUS_LEAVE),
            $periodStart,
            $periodEnd
        );

        return [
            'total_working_days' => $this->calculateWorkingDays($periodStart, $periodEnd),
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'holiday_days' => $holidayDays,
            'wfh_days' => $wfhDays,
            'office_days' => $officeDays,
            'missing_checkout_days' => $missingCheckoutDays,
            'total_attendance_days' => $presentDays + $lateDays + $halfDays,
            // Weekend/holiday work — excluded from prorate, paid via overtime pay
            'weekend_work_days' => $weekendWorkDays,
            'holiday_work_days' => $holidayWorkDays,
        ];
    }

    /**
     * Split leave-status attendance logs into paid vs unpaid leave days.
     *
     * A leave day is considered paid when it is covered by an approved
     * LeaveRequest whose LeaveType has a positive default_annual_quota
     * (i.e., the leave consumed quota). Any leave day that cannot be
     * matched to such a request (including unpaid leave types with
     * default_annual_quota = 0) is treated as unpaid and will still
     * prorate the basic salary.
     *
     * @param Employee $employee
     * @param \Illuminate\Support\Collection $leaveLogs AttendanceLog entries with status=leave
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @return array{0:int,1:int} [paidLeaveDays, unpaidLeaveDays]
     */
    protected function classifyLeaveDays(
        Employee $employee,
        $leaveLogs,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        if ($leaveLogs->isEmpty()) {
            return [0, 0];
        }

        $approvedLeaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get();

        $paid = 0;
        $unpaid = 0;

        foreach ($leaveLogs as $log) {
            $logDate = $log->date instanceof Carbon
                ? $log->date
                : Carbon::parse($log->date);

            $matching = $approvedLeaves->first(function (LeaveRequest $lr) use ($logDate) {
                return $lr->start_date->lte($logDate) && $lr->end_date->gte($logDate);
            });

            $quota = $matching && $matching->leaveType
                ? (int) ($matching->leaveType->default_annual_quota ?? 0)
                : 0;

            if ($quota > 0) {
                $paid++;
            } else {
                $unpaid++;
            }
        }

        return [$paid, $unpaid];
    }

    /**
     * Calculate working days in a period (excluding weekends).
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int
     */
    public function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $workingDays = 0;

        foreach ($period as $date) {
            // Exclude weekends (Saturday = 6, Sunday = 0)
            if (!$date->isWeekend()) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Calculate actual days worked (countable for payroll).
     *
     * Paid leave days (approved leave covered by a quota-consuming leave type)
     * are treated as worked so they do NOT prorate the basic salary. Unpaid
     * leave days and unexcused absences still reduce actual days worked.
     *
     * @param array $attendanceSummary
     * @param array $config Configuration for what to count
     * @return float
     */
    public function calculateActualDaysWorked(array $attendanceSummary, array $config = []): float
    {
        $countLate = $config['count_late'] ?? true;
        $halfDayRatio = $config['half_day_ratio'] ?? 0.5;
        $countWfh = $config['count_wfh'] ?? true;
        $countPaidLeave = $config['count_paid_leave'] ?? true;

        $days = $attendanceSummary['present_days'];

        if ($countLate) {
            $days += $attendanceSummary['late_days'];
        }

        // Half days count as configured ratio
        $days += $attendanceSummary['half_days'] * $halfDayRatio;

        // Paid leave (quota-consuming) protects basic salary from prorate.
        if ($countPaidLeave) {
            $days += $attendanceSummary['paid_leave_days'] ?? 0;
        }

        // Exclude weekend and holiday work days — those are paid via overtime, not prorate
        $days -= ($attendanceSummary['weekend_work_days'] ?? 0);

        return max(0.0, $days);
    }

    /**
     * Calculate prorate ratio.
     *
     * @param int $actualDaysWorked
     * @param int $totalWorkingDays
     * @return float
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
     * @param array $allowanceConfig Allowance configuration
     * @param array $attendanceSummary Attendance summary
     * @return float
     */
    public function calculateDailyAllowance(array $allowanceConfig, array $attendanceSummary): float
    {
        // If not attendance-based, return fixed amount
        $type = $allowanceConfig['type'] ?? 'fixed';
        if ($type === 'fixed') {
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
        if (!($allowanceConfig['count_wfh'] ?? true)) {
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
     * @param array $variableAllowances Array of allowance configurations
     * @param array $attendanceSummary Attendance summary
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
     *
     * @param float $fullBasicSalary
     * @param float $prorateRatio
     * @return float
     */
    public function calculateProratedSalary(float $fullBasicSalary, float $prorateRatio): float
    {
        return round($fullBasicSalary * $prorateRatio, 2);
    }

    /**
     * Get attendance summary formatted for payslip display.
     *
     * @param array $attendanceSummary
     * @return string
     */
    public function getAttendanceLabel(array $attendanceSummary): string
    {
        $actual = $attendanceSummary['present_days'] + $attendanceSummary['late_days'];
        $total = $attendanceSummary['total_working_days'];

        return "{$actual}/{$total} HK";
    }
}
