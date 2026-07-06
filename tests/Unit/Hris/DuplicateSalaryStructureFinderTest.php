<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\DuplicateSalaryStructureFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// The unique(employee_id, effective_date) constraint (migration
// 2026_07_06_000001) forbids the duplicates this finder exists to detect —
// it only matters for legacy data that predates the constraint. Drop it here
// to simulate that pre-migration state; RefreshDatabase's transaction wraps
// and reverts the whole test, so it's not restored manually.
function withoutSalaryStructureUniqueConstraint(callable $callback): void
{
    Schema::table('salary_structures', fn ($table) => $table->dropUnique(['employee_id', 'effective_date']));

    $callback();
}

beforeEach(function () {
    $this->finder = new DuplicateSalaryStructureFinder;
});

it('returns nothing when no employee has duplicate effective dates', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-01-01']);
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-06-01']);

    expect($this->finder->find())->toBeEmpty();
});

it('reports a group per employee/effective_date with more than one structure', function () {
    $employee = Employee::factory()->create();
    $other = Employee::factory()->create();

    withoutSalaryStructureUniqueConstraint(function () use ($employee, $other) {
        $dup1 = SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-05-01',
            'basic_salary' => 5_000_000,
            'daily_allowance_rate' => 50_000,
        ]);
        $dup2 = SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-05-01',
            'basic_salary' => 5_500_000,
            'daily_allowance_rate' => 50_000,
        ]);
        SalaryStructure::factory()->create(['employee_id' => $other->id, 'effective_date' => '2025-05-01']);

        $duplicates = $this->finder->find();

        expect($duplicates)->toHaveCount(1);

        $group = $duplicates->first();
        expect($group['employee_id'])->toBe($employee->id)
            ->and($group['effective_date'])->toBe('2025-05-01')
            ->and($group['duplicate_count'])->toBe(2)
            ->and($group['structure_ids'])->toEqual([$dup1->id, $dup2->id])
            ->and($group['basic_salaries'])->toEqual(['5000000.00', '5500000.00'])
            ->and($group['daily_allowance_rates'])->toEqual(['50000.00', '50000.00']);
    });
});
