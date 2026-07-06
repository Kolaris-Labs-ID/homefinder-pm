<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;

/**
 * The migration itself must fail fast — with a clear, actionable message —
 * if legacy duplicate (employee_id, effective_date) rows exist, rather than
 * bubbling up a raw unique-index QueryException. down() restores the exact
 * pre-migration schema (plain composite index, no unique constraint) so
 * up() can be re-run against it; RefreshDatabase's per-test transaction
 * reverts these schema changes afterward.
 */
function salaryStructureUniqueMigration()
{
    return require database_path('migrations/2026_07_06_000001_add_unique_effective_date_to_salary_structures_table.php');
}

it('aborts with a clear message instead of applying the constraint when duplicates exist', function () {
    $migration = salaryStructureUniqueMigration();
    $migration->down();

    $employee = Employee::factory()->create();
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-05-01']);
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-05-01']);

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'hris:salary-structures:report-duplicates');
});

it('applies the unique constraint when no duplicates exist', function () {
    $migration = salaryStructureUniqueMigration();
    $migration->down();

    $employee = Employee::factory()->create();
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-05-01']);

    $migration->up();

    expect(fn () => SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-05-01']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
