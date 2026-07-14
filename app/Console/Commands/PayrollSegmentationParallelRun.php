<?php

namespace App\Console\Commands;

use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Services\Hris\SalaryCoverageValidator;
use App\Services\Hris\SalarySegmentationService;
use App\Support\Money;
use Illuminate\Console\Command;

class PayrollSegmentationParallelRun extends Command
{
    protected $signature = 'hris:payroll:segmentation-parallel-run {batch}';

    protected $description = 'Report base-salary variance segmentation would introduce for a batch (read-only).';

    public function handle(): int
    {
        $batch = PayrunBatch::findOrFail($this->argument('batch'));
        $report = $this->buildReport($batch);

        $this->table(['Employee', 'Legacy base', 'Segmented base', 'Delta'], array_map(fn ($r) => [
            $r['employee_id'], $r['legacy_base'], $r['segmented_base'],
            Money::add($r['segmented_base'], '-'.$r['legacy_base']),
        ], $report['rows']));

        if (! empty($report['missing_history'])) {
            $this->error('Missing salary history: '.implode(', ', array_column($report['missing_history'], 'employee_id')));
        }

        return self::SUCCESS;
    }

    /** @return array{rows:array,missing_history:array} */
    public function buildReport(PayrunBatch $batch): array
    {
        $employees = Employee::active()->get();
        $segmentation = app(SalarySegmentationService::class);

        $missing = app(SalaryCoverageValidator::class)->validate(
            $employees, $batch->period_start_date, $batch->period_end_date
        );
        $missingIds = array_column($missing, 'employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            if (in_array($employee->id, $missingIds, true)) {
                continue;
            }
            $seg = $segmentation->compute(
                $employee, $batch->period_start_date, $batch->period_end_date
            );
            $rows[] = [
                'employee_id' => $employee->id,
                'legacy_base' => $this->legacyBase($employee, $batch),
                'segmented_base' => $seg['base_salary'],
            ];
        }

        return ['rows' => $rows, 'missing_history' => $missing];
    }

    protected function legacyBase(Employee $employee, PayrunBatch $batch): string
    {
        $structure = app(\App\Services\Hris\CompensationResolver::class)
            ->resolve($employee, $batch->period_end_date);

        return number_format((float) ($structure?->basic_salary ?? 0), Money::SCALE, '.', '');
    }
}
