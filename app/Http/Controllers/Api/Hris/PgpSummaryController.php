<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Services\Hris\PgpService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PgpSummaryController extends Controller
{
    public function show(Request $request, PgpService $pgpService): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $request->validate([
            'year' => ['nullable', 'required_with:quarter', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'required_with:year', 'integer', 'min:1', 'max:4'],
        ]);

        if ($request->filled('year') && $request->filled('quarter')) {
            $year = (int) $request->query('year');
            $quarter = (int) $request->query('quarter');
            $startMonth = ($quarter - 1) * 3 + 1;
            $period = $pgpService->getQuarterForDate(Carbon::create($year, $startMonth, 15));
            $points = $pgpService->getPointsForQuarter($employee, $quarter, $year);
        } else {
            $period = $pgpService->getCurrentQuarter();
            $points = $pgpService->getQuarterlyPoints($employee);
            $quarter = $period['quarter'];
            $year = $period['year'];
        }

        $recordsCount = TrainingRecord::query()
            ->where('employee_id', $employee->id)
            ->approvedInQuarter($quarter, $year)
            ->withPgpPoints()
            ->count();

        return response()->json([
            'data' => [
                'quarter' => $quarter,
                'year' => $year,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'points' => $points,
                'target' => PgpService::PGP_TARGET,
                'progress_percent' => round($pgpService->getProgressPercentage($points), 2),
                'projected_reward_idr' => $pgpService->calculateReward($points),
                'records_count' => $recordsCount,
            ],
        ]);
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = optional($request->user())->employee;

        abort_if(! $employee, 404, 'Employee profile not found.');

        return $employee;
    }
}
