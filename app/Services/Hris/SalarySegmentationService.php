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

        // Boundary dates inside the period: the structure active at period start,
        // plus every structure whose effective_date falls within (start, end].
        $boundaries = collect([$periodStart->copy()->startOfDay()]);
        foreach ($structures as $structure) {
            $eff = $structure->effective_date->copy()->startOfDay();
            if ($eff->gt($periodStart) && $eff->lte($periodEnd)) {
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
                : $periodEnd->copy()->endOfDay();

            $structure = $this->compensationResolver->resolveFrom($structures, $segStart);
            if (! $structure) {
                // No salary structure covers this segment. A money function must never
                // silently return an understated base — fail hard (defence-in-depth;
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

        $divisor = (int) $this->attendanceService->getAttendanceSummary($employee, $periodStart, $periodEnd)['total_working_days'];

        // Invariant: segments tile the period, so the per-segment scheduled days must
        // sum to the authoritative whole-period divisor. A mismatch means a boundary
        // bug that could over/understate pay — refuse to return a wrong number.
        if ($divisor !== $scheduledTotal) {
            throw new \RuntimeException(
                "Salary segmentation invariant violated: segment scheduled days {$scheduledTotal} != period divisor {$divisor}."
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
