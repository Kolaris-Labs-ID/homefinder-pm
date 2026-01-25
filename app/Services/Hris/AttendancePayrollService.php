<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
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

        return [
            'total_working_days' => $this->calculateWorkingDays($periodStart, $periodEnd),
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'half_days' => $halfDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'holiday_days' => $holidayDays,
            'wfh_days' => $wfhDays,
            'office_days' => $officeDays,
            'total_attendance_days' => $presentDays + $lateDays + $halfDays,
        ];
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
     * @param array $attendanceSummary
     * @param array $config Configuration for what to count
     * @return float
     */
    public function calculateActualDaysWorked(array $attendanceSummary, array $config = []): float
    {
        $countLate = $config['count_late'] ?? true;
        $halfDayRatio = $config['half_day_ratio'] ?? 0.5;
        $countWfh = $config['count_wfh'] ?? true;

        $days = $attendanceSummary['present_days'];

        if ($countLate) {
            $days += $attendanceSummary['late_days'];
        }

        // Half days count as configured ratio
        $days += $attendanceSummary['half_days'] * $halfDayRatio;

        return $days;
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
