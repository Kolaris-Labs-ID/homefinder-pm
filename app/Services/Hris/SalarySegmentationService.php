<?php

namespace App\Services\Hris;

use App\Exceptions\Hris\PayrollCoverageException;
use App\Models\Hris\Employee;
use App\Support\Money;
use Carbon\Carbon;

class SalarySegmentationService
{
    public function __construct(
        protected CompensationResolver $compensationResolver,
        protected AttendancePayrollService $attendanceService,
    ) {}

    /**
     * Compute the effective-date-segmented base salary for a period.
     *
     * @return array{base_salary:string, divisor:int, segments:array<int,array>}
     */
    public function compute(Employee $employee, Carbon $periodStart, Carbon $periodEnd, bool $prorateEnabled = false): array
    {
        $structures = $this->compensationResolver->structuresAsOf($employee, $periodEnd);

        // Clip accrual to the employment window within the period (I1). A mid-period
        // joiner/leaver accrues only while employed; the DIVISOR stays the full period
        // (B1), so their base is prorated by employed-days, not paid a full month.
        $effectiveStart = $periodStart->copy()->startOfDay();
        if ($employee->hire_date && $employee->hire_date->gt($effectiveStart)) {
            $effectiveStart = $employee->hire_date->copy()->startOfDay();
        }
        $effectiveEnd = $periodEnd->copy()->endOfDay();
        if ($employee->employment_end_date && $employee->employment_end_date->lt($periodEnd)) {
            $effectiveEnd = $employee->employment_end_date->copy()->endOfDay();
        }

        // Full-period scheduled working days — the common denominator for every segment.
        $divisor = (int) $this->attendanceService->getAttendanceSummary($employee, $periodStart, $periodEnd)['total_working_days'];

        // Not employed on any day of the period → nothing to accrue.
        if ($effectiveStart->gt($effectiveEnd)) {
            return [
                'base_salary' => bcadd('0', '0', Money::SCALE),
                'divisor' => $divisor,
                'scheduled_total' => 0,
                'numerator' => bcadd('0', '0', Money::SCALE),
                'segments' => [],
            ];
        }

        // Boundary dates inside the employment window: the structure active at
        // effectiveStart, plus every structure whose effective_date falls in (start, end].
        $boundaries = collect([$effectiveStart->copy()]);
        foreach ($structures as $structure) {
            $eff = $structure->effective_date->copy()->startOfDay();
            if ($eff->gt($effectiveStart) && $eff->lte($effectiveEnd)) {
                $boundaries->push($eff);
            }
        }
        $boundaries = $boundaries->unique(fn (Carbon $d) => $d->toDateString())
            ->sortBy(fn (Carbon $d) => $d->timestamp)->values();

        $segments = [];
        $numerator = '0.00';
        $scheduledTotal = 0;

        foreach ($boundaries as $i => $segStart) {
            $segEnd = isset($boundaries[$i + 1])
                ? $boundaries[$i + 1]->copy()->subDay()->endOfDay()
                : $effectiveEnd->copy();

            $structure = $this->compensationResolver->resolveFrom($structures, $segStart);
            if (! $structure) {
                // No salary structure covers this employed segment. A money function must
                // never silently return an understated base — fail hard (defence-in-depth;
                // the coverage preflight is the first line, this is the second).
                throw new PayrollCoverageException([[
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->user?->name,
                    'first_working_day' => $segStart->toDateString(),
                ]]);
            }

            $summary = $this->attendanceService->getAttendanceSummary($employee, $segStart, $segEnd);
            $scheduled = (int) $summary['total_working_days'];
            $payable = $prorateEnabled
                ? (int) round($this->attendanceService->calculateActualDaysWorked($summary))
                : $scheduled;

            // basic_salary is a decimal:2 cast (already a 2-dp string) — keep it in the
            // string money domain; never route it through a float.
            $basic = bcadd((string) $structure->basic_salary, '0', Money::SCALE);
            $segAmount = Money::mul($basic, $payable);
            $numerator = Money::add($numerator, $segAmount);
            $scheduledTotal += $scheduled;

            $segments[] = [
                'from' => $segStart->toDateString(),
                'to' => $segEnd->toDateString(),
                'salary_structure_id' => $structure->id,
                'effective_date' => $structure->effective_date->toDateString(),
                'basic_salary' => $basic,
                'scheduled_days' => $scheduled,
                'payable_days' => $payable,
                'amount' => $segAmount,
            ];
        }

        // Invariant: accrued (employed) scheduled days can never exceed the full-period
        // divisor. Exceeding it means a boundary bug that would overstate pay.
        if ($scheduledTotal > $divisor) {
            throw new \RuntimeException(
                "Salary segmentation invariant violated: accrued scheduled days {$scheduledTotal} > period divisor {$divisor}."
            );
        }

        $base = $divisor > 0
            ? Money::divideRound($numerator, $divisor)
            // No scheduled working days in the period: pay the newest structure's full basic.
            : bcadd((string) ($this->compensationResolver->resolveFrom($structures, $periodEnd)?->basic_salary ?? '0'), '0', Money::SCALE);

        return [
            'base_salary' => $base,
            'divisor' => (int) $divisor,
            'scheduled_total' => $scheduledTotal,
            'numerator' => $numerator,
            'segments' => $segments,
        ];
    }
}
