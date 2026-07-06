<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('exits successfully and reports no duplicates when the data is clean', function () {
    $employee = Employee::factory()->create();
    SalaryStructure::factory()->create(['employee_id' => $employee->id, 'effective_date' => '2025-01-01']);

    $this->artisan('hris:salary-structures:report-duplicates')
        ->expectsOutputToContain('No duplicate salary structures found')
        ->assertExitCode(0);
});

it('exits with a failure code and lists employee, dates, structure IDs and rates for duplicates', function () {
    $user = User::factory()->create(['name' => 'Jane Doe']);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    Schema::table('salary_structures', fn ($table) => $table->dropUnique(['employee_id', 'effective_date']));

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

    $this->artisan('hris:salary-structures:report-duplicates')
        ->expectsOutputToContain('Employee: Jane Doe')
        ->expectsOutputToContain('Effective date: 2025-05-01')
        ->expectsOutputToContain("Structure IDs: {$dup1->id}, {$dup2->id}")
        ->expectsOutputToContain('Basic salaries: 5000000.00 / 5500000.00')
        ->assertExitCode(1);
});
