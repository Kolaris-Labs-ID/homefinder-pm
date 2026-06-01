<?php

/**
 * Property-Based Tests for Gross Income Calculation
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\PayrollCalculationService;
use Carbon\Carbon;

/**
 * **Feature: hris-phase3-payroll, Property 8: Gross income calculation accuracy**
 *
 * For any payroll record, gross_income SHALL equal basic_salary + sum(fixed_allowances) + sum(variable_allowances).
 *
 * **Validates: Requirements 3.1**
 */
test('Property 8: Gross income calculation accuracy', function () {
    $service = new PayrollCalculationService(
        app(\App\Services\Hris\BpjsCalculationService::class),
        app(\App\Services\Hris\Pph21CalculationService::class)
    );

    // Run 100 iterations with random salary data
    for ($i = 0; $i < 100; $i++) {
        // Create test employee
        $employee = Employee::factory()->create();

        // Generate random salary components
        $basicSalary = fake()->randomFloat(2, 1000000, 50000000);

        // Generate random fixed allowances (0-5 items)
        $fixedAllowances = [];
        $fixedCount = fake()->numberBetween(0, 5);
        $totalFixedAllowances = 0;
        for ($j = 0; $j < $fixedCount; $j++) {
            $amount = fake()->randomFloat(2, 100000, 5000000);
            $fixedAllowances[] = [
                'type' => fake()->word(),
                'name' => fake()->word(),
                'amount' => $amount,
            ];
            $totalFixedAllowances += $amount;
        }

        // Generate random variable allowances (0-5 items)
        $variableAllowances = [];
        $variableCount = fake()->numberBetween(0, 5);
        $totalVariableAllowances = 0;
        for ($j = 0; $j < $variableCount; $j++) {
            $amount = fake()->randomFloat(2, 50000, 2000000);
            $variableAllowances[] = [
                'type' => fake()->word(),
                'name' => fake()->word(),
                'amount' => $amount,
            ];
            $totalVariableAllowances += $amount;
        }

        // Create salary structure
        $salaryStructure = SalaryStructure::factory()
            ->for($employee)
            ->state([
                'basic_salary' => $basicSalary,
                'allowances_fixed' => $fixedAllowances,
                'allowances_variable' => $variableAllowances,
                'effective_date' => Carbon::now()->subDays(10),
                'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            ])
            ->create();

        // Create payrun batch
        $batch = PayrunBatch::factory()
            ->state([
                'period_start_date' => Carbon::now()->subDays(30),
                'period_end_date' => Carbon::now(),
                'pay_date' => Carbon::now()->addDays(5),
            ])
            ->create();

        // Calculate payroll
        $record = $service->calculateEmployee($employee, $batch);

        // Calculate expected gross income
        $expectedGrossIncome = $basicSalary + $totalFixedAllowances + $totalVariableAllowances;

        // Property: gross_income SHALL equal basic_salary + sum(fixed_allowances) + sum(variable_allowances)
        expect($record->gross_income)->toEqualWithDelta(
            $expectedGrossIncome,
            0.01,
            "Gross income calculation incorrect. " .
            "Basic: {$basicSalary}, Fixed: {$totalFixedAllowances}, Variable: {$totalVariableAllowances}, " .
            "Expected: {$expectedGrossIncome}, Got: {$record->gross_income}"
        );

        // Verify the calculation matches the formula exactly
        $calculatedGross = $record->basic_salary + $record->total_fixed_allowances + $record->total_variable_allowances;
        expect($record->gross_income)->toEqualWithDelta(
            $calculatedGross,
            0.01,
            "Gross income should equal basic_salary + total_fixed_allowances + total_variable_allowances"
        );

        // Clean up
        $record->delete();
        $batch->delete();
        $salaryStructure->delete();
        $employee->delete();
    }
})->group('property-test', 'hris-payroll', 'gross-income');

/**
 * **Feature: hris-phase3-payroll, Property 8 Edge Cases: Gross income boundary conditions**
 *
 * Tests edge cases for gross income calculation including:
 * - No allowances (only basic salary)
 * - Only fixed allowances
 * - Only variable allowances
 * - Multiple allowances of each type
 * - Very high salary components
 * - Minimum salary components
 *
 * **Validates: Requirements 3.1**
 */
test('Property 8 Edge Cases: Gross income boundary conditions', function () {
    $service = new PayrollCalculationService(
        app(\App\Services\Hris\BpjsCalculationService::class),
        app(\App\Services\Hris\Pph21CalculationService::class)
    );

    // Edge case 1: No allowances (only basic salary)
    $employee = Employee::factory()->create();
    $basicSalary = 10000000;
    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    expect($record->gross_income)->toEqualWithDelta($basicSalary, 0.01);
    expect($record->total_fixed_allowances)->toEqualWithDelta(0.0, 0.01);
    expect($record->total_variable_allowances)->toEqualWithDelta(0.0, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 2: Only fixed allowances
    $employee = Employee::factory()->create();
    $basicSalary = 10000000;
    $fixedAllowances = [
        ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 2000000],
        ['type' => 'family', 'name' => 'Family Allowance', 'amount' => 1000000],
    ];
    $totalFixed = 3000000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => [],
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    expect($record->gross_income)->toEqualWithDelta($basicSalary + $totalFixed, 0.01);
    expect($record->total_fixed_allowances)->toEqualWithDelta($totalFixed, 0.01);
    expect($record->total_variable_allowances)->toEqualWithDelta(0.0, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 3: Only variable allowances
    $employee = Employee::factory()->create();
    $basicSalary = 10000000;
    $variableAllowances = [
        ['type' => 'transport', 'name' => 'Transport Allowance', 'amount' => 500000],
        ['type' => 'meal', 'name' => 'Meal Allowance', 'amount' => 300000],
    ];
    $totalVariable = 800000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [],
            'allowances_variable' => $variableAllowances,
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    expect($record->gross_income)->toEqualWithDelta($basicSalary + $totalVariable, 0.01);
    expect($record->total_fixed_allowances)->toEqualWithDelta(0.0, 0.01);
    expect($record->total_variable_allowances)->toEqualWithDelta($totalVariable, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 4: Multiple allowances of each type
    $employee = Employee::factory()->create();
    $basicSalary = 15000000;
    $fixedAllowances = [
        ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 3000000],
        ['type' => 'family', 'name' => 'Family Allowance', 'amount' => 1500000],
        ['type' => 'housing', 'name' => 'Housing Allowance', 'amount' => 2000000],
    ];
    $totalFixed = 6500000;

    $variableAllowances = [
        ['type' => 'transport', 'name' => 'Transport Allowance', 'amount' => 800000],
        ['type' => 'meal', 'name' => 'Meal Allowance', 'amount' => 500000],
        ['type' => 'performance', 'name' => 'Performance Bonus', 'amount' => 1000000],
    ];
    $totalVariable = 2300000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    $expectedGross = $basicSalary + $totalFixed + $totalVariable;
    expect($record->gross_income)->toEqualWithDelta($expectedGross, 0.01);
    expect($record->total_fixed_allowances)->toEqualWithDelta($totalFixed, 0.01);
    expect($record->total_variable_allowances)->toEqualWithDelta($totalVariable, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 5: Very high salary components
    $employee = Employee::factory()->create();
    $basicSalary = 50000000;
    $fixedAllowances = [
        ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 10000000],
    ];
    $totalFixed = 10000000;

    $variableAllowances = [
        ['type' => 'performance', 'name' => 'Performance Bonus', 'amount' => 5000000],
    ];
    $totalVariable = 5000000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    $expectedGross = $basicSalary + $totalFixed + $totalVariable;
    expect($record->gross_income)->toEqualWithDelta($expectedGross, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 6: Minimum salary components
    $employee = Employee::factory()->create();
    $basicSalary = 1000000;
    $fixedAllowances = [
        ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 100000],
    ];
    $totalFixed = 100000;

    $variableAllowances = [
        ['type' => 'transport', 'name' => 'Transport Allowance', 'amount' => 50000],
    ];
    $totalVariable = 50000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'effective_date' => Carbon::now()->subDays(10),
            // Pinned to gross: this property covers salary-component summation.
            // gross_up adds a taxable tunjangan pajak to gross (covered by
            // Pph21BearerNetPayTest); the random factory default made this flaky.
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        ])
        ->create();

    $batch = PayrunBatch::factory()
        ->state([
            'period_start_date' => Carbon::now()->subDays(30),
            'period_end_date' => Carbon::now(),
            'pay_date' => Carbon::now()->addDays(5),
        ])
        ->create();

    $record = $service->calculateEmployee($employee, $batch);
    $expectedGross = $basicSalary + $totalFixed + $totalVariable;
    expect($record->gross_income)->toEqualWithDelta($expectedGross, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();
})->group('property-test', 'hris-payroll', 'gross-income');
