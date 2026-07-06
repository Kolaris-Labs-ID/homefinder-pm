<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves the salary structure (and thus compensation) in effect for an
 * employee on a specific WORK DATE — not the payroll period end.
 *
 * A structure is "active for a date" when it has the newest effective_date
 * that does not exceed that date (open-ended effective-dating; no end_date).
 * Shared by Weekly Allowance, Monthly Payroll, and Overtime so a mid-period
 * daily_allowance_rate change is applied per day rather than retroactively.
 */
class CompensationResolver
{
    /**
     * Resolve the single active salary structure for an employee on a work date.
     */
    public function resolve(Employee|int $employee, Carbon $workDate): ?SalaryStructure
    {
        return SalaryStructure::forEmployee($this->employeeId($employee))
            ->activeForDate($workDate)
            ->first();
    }

    /**
     * Load every structure effective on or before $asOf once, newest-first, so a
     * date range can be resolved per day in memory without one query per day.
     */
    public function structuresAsOf(Employee|int $employee, Carbon $asOf): Collection
    {
        return SalaryStructure::forEmployee($this->employeeId($employee))
            ->activeForDate($asOf)
            ->get();
    }

    /**
     * From a preloaded newest-first collection, pick the structure effective on $date.
     */
    public function resolveFrom(Collection $structures, Carbon $date): ?SalaryStructure
    {
        return $structures->first(fn (SalaryStructure $s) => $s->effective_date->lte($date));
    }

    /**
     * Daily allowance rate effective on $date (0.0 when no structure is yet effective).
     */
    public function dailyRateFrom(Collection $structures, Carbon $date): float
    {
        $structure = $this->resolveFrom($structures, $date);

        return $structure ? (float) $structure->daily_allowance_rate : 0.0;
    }

    private function employeeId(Employee|int $employee): int
    {
        return $employee instanceof Employee ? $employee->id : $employee;
    }
}
