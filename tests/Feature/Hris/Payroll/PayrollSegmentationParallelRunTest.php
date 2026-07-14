<?php

use App\Console\Commands\PayrollSegmentationParallelRun;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports the base-salary variance a raise would introduce, without persisting', function () {
    $e = Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);
    SalaryStructure::factory()->create(['employee_id' => $e->id, 'effective_date' => '2026-06-01', 'basic_salary' => 6000000, 'prorate_enabled' => false, 'allowances_fixed' => [], 'bpjs_enabled' => false, 'weekly_allowance_enabled' => false, 'daily_allowance_rate' => 0]);
    SalaryStructure::factory()->create(['employee_id' => $e->id, 'effective_date' => '2026-07-13', 'basic_salary' => 7500000, 'prorate_enabled' => false, 'allowances_fixed' => [], 'bpjs_enabled' => false, 'weekly_allowance_enabled' => false, 'daily_allowance_rate' => 0]);
    $batch = PayrunBatch::factory()->create(['period_start_date' => '2026-07-01', 'period_end_date' => '2026-07-31', 'status' => PayrunBatch::STATUS_DRAFT]);

    $report = app(PayrollSegmentationParallelRun::class)->buildReport($batch);

    expect($report['rows'][0]['segmented_base'])->toBe('6978260.87');
    expect($report['rows'][0]['legacy_base'])->toBe('7500000.00');
    expect($report['missing_history'])->toBeEmpty();
    expect(\App\Models\Hris\PayrollRecord::count())->toBe(0);
});
