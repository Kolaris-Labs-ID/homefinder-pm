<?php

namespace App\Services\Hris;

use App\Exceptions\Hris\PayrollCoverageException;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class SalaryCoverageValidator
{
    public function __construct(protected CompensationResolver $compensationResolver) {}

    /** @return array<int,array{employee_id:int,employee_name:?string,first_working_day:string}> */
    public function validate(Collection $employees, Carbon $periodStart, Carbon $periodEnd): array
    {
        $gaps = [];
        foreach ($employees as $employee) {
            // Check coverage from the employee's first EMPLOYED working day, not the
            // period start (I1): a mid-period joiner only needs a structure from hire.
            $windowStart = $periodStart->copy()->startOfDay();
            if ($employee->hire_date && $employee->hire_date->gt($windowStart)) {
                $windowStart = $employee->hire_date->copy()->startOfDay();
            }
            $windowEnd = $periodEnd->copy()->startOfDay();
            if ($employee->employment_end_date && $employee->employment_end_date->lt($windowEnd)) {
                $windowEnd = $employee->employment_end_date->copy()->startOfDay();
            }

            $firstWorkingDay = $this->firstScheduledWorkingDay($windowStart, $windowEnd);
            if (! $firstWorkingDay) {
                continue; // not employed on any working day in the period — nothing to cover
            }

            if (! $this->compensationResolver->resolve($employee, $firstWorkingDay)) {
                $gaps[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->user?->name,
                    'first_working_day' => $firstWorkingDay->toDateString(),
                ];
            }
        }

        return $gaps;
    }

    public function assert(Collection $employees, Carbon $periodStart, Carbon $periodEnd): void
    {
        $gaps = $this->validate($employees, $periodStart, $periodEnd);
        if (! empty($gaps)) {
            throw new PayrollCoverageException($gaps);
        }
    }

    protected function firstScheduledWorkingDay(Carbon $periodStart, Carbon $periodEnd): ?Carbon
    {
        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $date) {
            if (! $date->isWeekend()) {
                return $date->copy();
            }
        }

        return null;
    }
}
