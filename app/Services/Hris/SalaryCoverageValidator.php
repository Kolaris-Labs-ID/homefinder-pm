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
        $firstWorkingDay = $this->firstScheduledWorkingDay($periodStart, $periodEnd);
        if (! $firstWorkingDay) {
            return [];
        }

        $gaps = [];
        foreach ($employees as $employee) {
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
