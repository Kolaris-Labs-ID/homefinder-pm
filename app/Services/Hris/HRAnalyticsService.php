<?php

namespace App\Services\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\JobPosting;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HRAnalyticsService
{
    /**
     * Get headcount grouped by department.
     * Returns active employees count per department.
     *
     * @return Collection Collection with department_id, department_name, and count
     */
    public function getHeadcountByDepartment(): Collection
    {
        return EmployeePosition::query()
            ->join('employees', 'employee_positions.employee_id', '=', 'employees.id')
            ->join('departments', 'employee_positions.department_id', '=', 'departments.id')
            ->where('employees.is_active', true)
            ->where('employee_positions.is_primary', true)
            ->whereNull('employee_positions.end_date')
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(DISTINCT employees.id) as count')
            )
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('departments.name')
            ->get();
    }

    /**
     * Get headcount grouped by employment status.
     * Returns active employees count per employment status.
     *
     * @return Collection Collection with employment_status and count
     */
    public function getHeadcountByEmploymentStatus(): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->select(
                'employment_status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('employment_status')
            ->orderBy('employment_status')
            ->get();
    }


    /**
     * Calculate turnover rate for a given date range.
     * Formula: (employees_left / average_headcount) * 100
     *
     * @param Carbon $startDate Start of the period
     * @param Carbon $endDate End of the period
     * @return float Turnover rate as percentage
     */
    public function calculateTurnoverRate(Carbon $startDate, Carbon $endDate): float
    {
        // Count employees who left during the period
        $employeesLeft = Employee::query()
            ->whereNotNull('employment_end_date')
            ->whereBetween('employment_end_date', [$startDate, $endDate])
            ->count();

        // Calculate average headcount
        // Headcount at start: employees hired before or on start date and not terminated before start date
        $headcountAtStart = Employee::query()
            ->where('hire_date', '<=', $startDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('employment_end_date')
                    ->orWhere('employment_end_date', '>=', $startDate);
            })
            ->count();

        // Headcount at end: employees hired before or on end date and not terminated before end date
        $headcountAtEnd = Employee::query()
            ->where('hire_date', '<=', $endDate)
            ->where(function ($query) use ($endDate) {
                $query->whereNull('employment_end_date')
                    ->orWhere('employment_end_date', '>=', $endDate);
            })
            ->count();

        $averageHeadcount = ($headcountAtStart + $headcountAtEnd) / 2;

        if ($averageHeadcount == 0) {
            return 0;
        }

        return ($employeesLeft / $averageHeadcount) * 100;
    }


    /**
     * Get age distribution of active employees.
     * Groups employees into age brackets: under 25, 25-34, 35-44, 45-54, 55+
     *
     * @return array Array with age brackets and counts
     */
    public function getAgeDistribution(): array
    {
        $today = Carbon::today();

        $employees = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('date_of_birth')
            ->get();

        $distribution = [
            'under_25' => 0,
            '25_34' => 0,
            '35_44' => 0,
            '45_54' => 0,
            '55_plus' => 0,
        ];

        foreach ($employees as $employee) {
            $age = $employee->date_of_birth->diffInYears($today);

            if ($age < 25) {
                $distribution['under_25']++;
            } elseif ($age >= 25 && $age <= 34) {
                $distribution['25_34']++;
            } elseif ($age >= 35 && $age <= 44) {
                $distribution['35_44']++;
            } elseif ($age >= 45 && $age <= 54) {
                $distribution['45_54']++;
            } else {
                $distribution['55_plus']++;
            }
        }

        return $distribution;
    }

    /**
     * Get tenure distribution of active employees.
     * Groups employees by years of service.
     *
     * @return array Array with tenure brackets and counts
     */
    public function getTenureDistribution(): array
    {
        $today = Carbon::today();

        $employees = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('hire_date')
            ->get();

        $distribution = [
            'less_than_1_year' => 0,
            '1_2_years' => 0,
            '3_5_years' => 0,
            '6_10_years' => 0,
            'more_than_10_years' => 0,
        ];

        foreach ($employees as $employee) {
            $yearsOfService = $employee->hire_date->diffInYears($today);

            if ($yearsOfService < 1) {
                $distribution['less_than_1_year']++;
            } elseif ($yearsOfService >= 1 && $yearsOfService <= 2) {
                $distribution['1_2_years']++;
            } elseif ($yearsOfService >= 3 && $yearsOfService <= 5) {
                $distribution['3_5_years']++;
            } elseif ($yearsOfService >= 6 && $yearsOfService <= 10) {
                $distribution['6_10_years']++;
            } else {
                $distribution['more_than_10_years']++;
            }
        }

        return $distribution;
    }


    /**
     * Get leave usage summary for a given year.
     * Shows leave days used vs available by leave type.
     *
     * @param int $year The year to get summary for
     * @return Collection Collection with leave type, used days, and available days
     */
    public function getLeaveUsageSummary(int $year): Collection
    {
        return LeaveBalance::query()
            ->join('leave_types', 'leave_balances.leave_type_id', '=', 'leave_types.id')
            ->where('leave_balances.year', $year)
            ->select(
                'leave_types.id as leave_type_id',
                'leave_types.name as leave_type_name',
                DB::raw('SUM(leave_balances.used_days) as total_used'),
                DB::raw('SUM(leave_balances.starting_balance) as total_available'),
                DB::raw('SUM(leave_balances.remaining_days) as total_remaining')
            )
            ->groupBy('leave_types.id', 'leave_types.name')
            ->orderBy('leave_types.name')
            ->get();
    }

    /**
     * Get attendance KPIs for a given date range.
     * Shows attendance rate, late percentage, and average working hours.
     *
     * @param Carbon $startDate Start of the period
     * @param Carbon $endDate End of the period
     * @return array Array with attendance KPIs
     */
    public function getAttendanceKPIs(Carbon $startDate, Carbon $endDate): array
    {
        $logs = AttendanceLog::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $totalLogs = $logs->count();

        if ($totalLogs === 0) {
            return [
                'attendance_rate' => 0,
                'late_percentage' => 0,
                'average_working_hours' => 0,
                'total_records' => 0,
            ];
        }

        // Count present (present + late + half_day)
        $presentCount = $logs->whereIn('status', [
            AttendanceLog::STATUS_PRESENT,
            AttendanceLog::STATUS_LATE,
            AttendanceLog::STATUS_HALF_DAY,
        ])->count();

        // Count late
        $lateCount = $logs->where('status', AttendanceLog::STATUS_LATE)->count();

        // Calculate average working hours (only for logs with both check-in and check-out)
        $logsWithBothTimes = $logs->filter(function ($log) {
            return $log->check_in_time && $log->check_out_time;
        });

        $totalWorkingMinutes = 0;
        foreach ($logsWithBothTimes as $log) {
            $totalWorkingMinutes += $log->check_in_time->diffInMinutes($log->check_out_time);
        }

        $averageWorkingHours = $logsWithBothTimes->count() > 0
            ? round($totalWorkingMinutes / $logsWithBothTimes->count() / 60, 2)
            : 0;

        return [
            'attendance_rate' => round(($presentCount / $totalLogs) * 100, 2),
            'late_percentage' => round(($lateCount / $totalLogs) * 100, 2),
            'average_working_hours' => $averageWorkingHours,
            'total_records' => $totalLogs,
        ];
    }


    /**
     * Get recruitment metrics for a given date range.
     * Includes job postings, applicant counts, and time-to-hire metrics.
     *
     * @param Carbon $startDate Start of the period
     * @param Carbon $endDate End of the period
     * @return array Array with recruitment metrics
     */
    public function getRecruitmentMetrics(Carbon $startDate, Carbon $endDate): array
    {
        // Job postings created in the period
        $jobPostingsCreated = JobPosting::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Job postings published in the period
        $jobPostingsPublished = JobPosting::query()
            ->whereBetween('published_at', [$startDate, $endDate])
            ->count();

        // Job postings closed in the period
        $jobPostingsClosed = JobPosting::query()
            ->whereBetween('closed_at', [$startDate, $endDate])
            ->count();

        // Total applicants in the period
        $totalApplicants = Applicant::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Applicants by status
        $applicantsByStatus = Applicant::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Hired applicants in the period
        $hiredApplicants = Applicant::query()
            ->where('status', Applicant::STATUS_HIRED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->get();

        // Calculate average time-to-hire (days from application to hire)
        $timeToHireSum = 0;
        $hiredCount = $hiredApplicants->count();

        foreach ($hiredApplicants as $applicant) {
            $timeToHireSum += $applicant->created_at->diffInDays($applicant->updated_at);
        }

        $averageTimeToHire = $hiredCount > 0 ? round($timeToHireSum / $hiredCount, 1) : 0;

        // Conversion rate (hired / total applicants)
        $conversionRate = $totalApplicants > 0
            ? round(($hiredCount / $totalApplicants) * 100, 2)
            : 0;

        return [
            'job_postings_created' => $jobPostingsCreated,
            'job_postings_published' => $jobPostingsPublished,
            'job_postings_closed' => $jobPostingsClosed,
            'total_applicants' => $totalApplicants,
            'applicants_by_status' => $applicantsByStatus,
            'hired_count' => $hiredCount,
            'average_time_to_hire_days' => $averageTimeToHire,
            'conversion_rate' => $conversionRate,
        ];
    }
}
