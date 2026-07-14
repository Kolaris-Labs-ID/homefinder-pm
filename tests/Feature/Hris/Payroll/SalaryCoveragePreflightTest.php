<?php

use App\Exceptions\Hris\PayrollCoverageException;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\SalaryCoverageValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes when every eligible employee has a structure effective on/before the first working day', function () {
    $e = Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);
    SalaryStructure::factory()->create(['employee_id' => $e->id, 'effective_date' => '2026-06-01', 'basic_salary' => 6000000]);

    $gaps = app(SalaryCoverageValidator::class)->validate(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($gaps)->toBeEmpty();
});

it('reports an employee with no structure covering the period start', function () {
    $e = Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);
    SalaryStructure::factory()->create(['employee_id' => $e->id, 'effective_date' => '2026-07-20', 'basic_salary' => 6000000]);

    $gaps = app(SalaryCoverageValidator::class)->validate(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($gaps)->toHaveCount(1);
    expect($gaps[0]['employee_id'])->toBe($e->id);
});

it('throws PayrollCoverageException from assert() when coverage is incomplete', function () {
    $e = Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);

    app(SalaryCoverageValidator::class)->assert(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );
})->throws(PayrollCoverageException::class);

it('passes a mid-period joiner whose structure starts on their hire date (I1)', function () {
    $e = Employee::factory()->create(['hire_date' => '2026-07-15', 'is_active' => true]);
    SalaryStructure::factory()->create(['employee_id' => $e->id, 'effective_date' => '2026-07-15', 'basic_salary' => 6000000]);

    $gaps = app(SalaryCoverageValidator::class)->validate(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($gaps)->toBeEmpty();
});

it('reports a mid-period joiner with no structure at all (gap at their hire day)', function () {
    $e = Employee::factory()->create(['hire_date' => '2026-07-15', 'is_active' => true]);

    $gaps = app(SalaryCoverageValidator::class)->validate(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($gaps)->toHaveCount(1);
    expect($gaps[0]['first_working_day'])->toBe('2026-07-15');
});

it('skips an employee not employed on any working day in the period (no false gap)', function () {
    $e = Employee::factory()->create(['hire_date' => '2026-08-01', 'is_active' => true]); // joins after July

    $gaps = app(SalaryCoverageValidator::class)->validate(
        collect([$e]), Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($gaps)->toBeEmpty();
});
