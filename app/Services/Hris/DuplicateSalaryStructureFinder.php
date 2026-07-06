<?php

namespace App\Services\Hris;

use App\Models\Hris\SalaryStructure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only detector for employee/effective_date combinations with more than
 * one salary structure. Only legacy data can trigger this — the
 * unique(employee_id, effective_date) constraint (migration
 * 2026_07_06_000001) forbids new duplicates — so this exists to gate that
 * migration and give HR/Engineering a manual-review report beforehand.
 */
class DuplicateSalaryStructureFinder
{
    public function find(): Collection
    {
        $groups = DB::table('salary_structures')
            ->select('employee_id')
            ->selectRaw('DATE(effective_date) as effective_date')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('employee_id', DB::raw('DATE(effective_date)'))
            ->having('duplicate_count', '>', 1)
            ->orderBy('employee_id')
            ->get();

        return $groups->map(function ($group) {
            $rows = SalaryStructure::where('employee_id', $group->employee_id)
                ->whereDate('effective_date', $group->effective_date)
                ->orderBy('id')
                ->get(['id', 'basic_salary', 'daily_allowance_rate']);

            return [
                'employee_id' => $group->employee_id,
                'effective_date' => $group->effective_date,
                'duplicate_count' => (int) $group->duplicate_count,
                'structure_ids' => $rows->pluck('id')->all(),
                'basic_salaries' => $rows->pluck('basic_salary')->map(fn ($v) => (string) $v)->all(),
                'daily_allowance_rates' => $rows->pluck('daily_allowance_rate')->map(fn ($v) => (string) $v)->all(),
            ];
        });
    }
}
