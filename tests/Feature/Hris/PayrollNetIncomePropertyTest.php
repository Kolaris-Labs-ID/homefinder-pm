<?php

/**
 * Property-Based Tests for Net Income Calculation
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\PayrollCalculationService;
use Carbon\Carbon;

/**
 * **Feature: hris-phase3-payroll, Property 13: Net income calculation formula**
 *
 * For any payroll record, net_income SHALL equal gross_income - pph21_amount - employee_bpjs_total - other_deductions.
 *
 * **Validates: Requirements 3.9**
 */
test('Property 13: Net income calculation formula', function () {
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

        // Generate random deductions (0-3 items)
        $deductions = [];
        $deductionCount = fake()->numberBetween(0, 3);
        $totalDeductions = 0;
        for ($j = 0; $j < $deductionCount; $j++) {
            $amount = fake()->randomFloat(2, 50000, 1000000);
            $deductions[] = [
                'type' => fake()->word(),
                'name' => fake()->word(),
                'amount' => $amount,
            ];
            $totalDeductions += $amount;
        }

        // Create salary structure with random PPh21 method
        $pph21Methods = ['netto', 'gross', 'gross_up'];
        $jkkRiskLevels = ['very_low', 'low', 'medium', 'high', 'very_high'];

        $salaryStructure = SalaryStructure::factory()
            ->for($employee)
            ->state([
                'basic_salary' => $basicSalary,
                'allowances_fixed' => $fixedAllowances,
                'allowances_variable' => $variableAllowances,
                'deductions' => $deductions,
                'pph21_method' => fake()->randomElement($pph21Methods),
                'jkk_risk_level' => fake()->randomElement($jkkRiskLevels),
                'effective_date' => Carbon::now()->subDays(10),
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

        // Calculate expected net income using the formula:
        // net_income = gross_income - pph21_amount - employee_bpjs_total - other_deductions
        $expectedNetIncome = $record->gross_income
            - $record->pph21_amount
            - $record->getTotalBpjsEmployeeAttribute()
            - $record->other_deductions;

        // Property: net_income SHALL equal gross_income - pph21_amount - employee_bpjs_total - other_deductions
        expect($record->net_income)->toEqualWithDelta(
            $expectedNetIncome,
            0.01,
            "Net income calculation incorrect. " .
            "Gross: {$record->gross_income}, PPh21: {$record->pph21_amount}, " .
            "Employee BPJS: {$record->getTotalBpjsEmployeeAttribute()}, " .
            "Other Deductions: {$record->other_deductions}, " .
            "Expected: {$expectedNetIncome}, Got: {$record->net_income}"
        );

        // Verify the calculation matches the formula exactly
        $calculatedNetIncome = $record->gross_income
            - $record->pph21_amount
            - ($record->bpjs_kesehatan_employee + $record->bpjs_jht_employee + $record->bpjs_jp_employee)
            - $record->other_deductions;

        expect($record->net_income)->toEqualWithDelta(
            $calculatedNetIncome,
            0.01,
            "Net income should equal gross_income - pph21_amount - employee_bpjs_total - other_deductions"
        );

        // Verify that net income is less than or equal to gross income
        expect($record->net_income)->toBeLessThanOrEqual($record->gross_income);

        // Clean up
        $record->delete();
        $batch->delete();
        $salaryStructure->delete();
        $employee->delete();
    }
})->group('property-test', 'hris-payroll', 'net-income');

/**
 * **Feature: hris-phase3-payroll, Property 13 Edge Cases: Net income boundary conditions**
 *
 * Tests edge cases for net income calculation including:
 * - No deductions (only gross income)
 * - Only PPh21 deduction
 * - Only BPJS deductions
 * - Only other deductions
 * - All deductions combined
 * - Very high salary with high deductions
 * - Minimum salary with minimal deductions
 *
 * **Validates: Requirements 3.9**
 */
test('Property 13 Edge Cases: Net income boundary conditions', function () {
    $service = new PayrollCalculationService(
        app(\App\Services\Hris\BpjsCalculationService::class),
        app(\App\Services\Hris\Pph21CalculationService::class)
    );

    // Edge case 1: No other deductions (only BPJS and PPh21)
    $employee = Employee::factory()->create();
    $basicSalary = 10000000;
    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => 'gross',
            'effective_date' => Carbon::now()->subDays(10),
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
    $expectedNetIncome = $record->gross_income
        - $record->pph21_amount
        - $record->getTotalBpjsEmployeeAttribute()
        - $record->other_deductions;

    expect($record->net_income)->toEqualWithDelta($expectedNetIncome, 0.01);
    expect($record->other_deductions)->toEqualWithDelta(0.0, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 2: With other deductions
    $employee = Employee::factory()->create();
    $basicSalary = 15000000;
    $otherDeductions = [
        ['type' => 'loan', 'name' => 'Loan Deduction', 'amount' => 500000],
        ['type' => 'insurance', 'name' => 'Insurance', 'amount' => 200000],
    ];
    $totalOtherDeductions = 700000;

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [
                ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 2000000],
            ],
            'allowances_variable' => [
                ['type' => 'transport', 'name' => 'Transport Allowance', 'amount' => 500000],
            ],
            'deductions' => $otherDeductions,
            'pph21_method' => 'gross',
            'effective_date' => Carbon::now()->subDays(10),
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
    $expectedNetIncome = $record->gross_income
        - $record->pph21_amount
        - $record->getTotalBpjsEmployeeAttribute()
        - $record->other_deductions;

    expect($record->net_income)->toEqualWithDelta($expectedNetIncome, 0.01);
    expect($record->other_deductions)->toEqualWithDelta($totalOtherDeductions, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 3: Very high salary with high deductions
    $employee = Employee::factory()->create();
    $basicSalary = 50000000;
    $fixedAllowances = [
        ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 10000000],
    ];
    $variableAllowances = [
        ['type' => 'performance', 'name' => 'Performance Bonus', 'amount' => 5000000],
    ];
    $otherDeductions = [
        ['type' => 'loan', 'name' => 'Loan Deduction', 'amount' => 2000000],
    ];

    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'deductions' => $otherDeductions,
            'pph21_method' => 'gross',
            'effective_date' => Carbon::now()->subDays(10),
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
    $expectedNetIncome = $record->gross_income
        - $record->pph21_amount
        - $record->getTotalBpjsEmployeeAttribute()
        - $record->other_deductions;

    expect($record->net_income)->toEqualWithDelta($expectedNetIncome, 0.01);
    expect($record->net_income)->toBeLessThanOrEqual($record->gross_income);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 4: Minimum salary with minimal deductions
    $employee = Employee::factory()->create();
    $basicSalary = 1000000;
    $salaryStructure = SalaryStructure::factory()
        ->for($employee)
        ->state([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => 'netto',
            'effective_date' => Carbon::now()->subDays(10),
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
    $expectedNetIncome = $record->gross_income
        - $record->pph21_amount
        - $record->getTotalBpjsEmployeeAttribute()
        - $record->other_deductions;

    expect($record->net_income)->toEqualWithDelta($expectedNetIncome, 0.01);

    $record->delete();
    $batch->delete();
    $salaryStructure->delete();
    $employee->delete();

    // Edge case 5: Different PPh21 methods
    foreach (['netto', 'gross', 'gross_up'] as $method) {
        $employee = Employee::factory()->create();
        $basicSalary = 20000000;
        $salaryStructure = SalaryStructure::factory()
            ->for($employee)
            ->state([
                'basic_salary' => $basicSalary,
                'allowances_fixed' => [
                    ['type' => 'position', 'name' => 'Position Allowance', 'amount' => 3000000],
                ],
                'allowances_variable' => [
                    ['type' => 'transport', 'name' => 'Transport Allowance', 'amount' => 500000],
                ],
                'deductions' => [
                    ['type' => 'loan', 'name' => 'Loan Deduction', 'amount' => 500000],
                ],
                'pph21_method' => $method,
                'effective_date' => Carbon::now()->subDays(10),
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
        $expectedNetIncome = $record->gross_income
            - $record->pph21_amount
            - $record->getTotalBpjsEmployeeAttribute()
            - $record->other_deductions;

        expect($record->net_income)->toEqualWithDelta($expectedNetIncome, 0.01,
            "Net income calculation failed for PPh21 method: {$method}"
        );

        $record->delete();
        $batch->delete();
        $salaryStructure->delete();
        $employee->delete();
    }
})->group('property-test', 'hris-payroll', 'net-income');
