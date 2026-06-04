<?php

/**
 * Property-Based Tests for SalaryStructure Model
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;

beforeEach(function () {
    // Clean up before each test
    SalaryStructure::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 1: Salary structure data persistence round-trip**
 *
 * For any valid salary structure with basic_salary, allowances (fixed/variable),
 * deductions, and PPh method, storing and retrieving the structure SHALL return
 * equivalent data.
 *
 * **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
 */
test('Property 1: Salary structure data persistence round-trip', function () {
    // Create a single employee for all iterations to speed up tests
    $employee = Employee::factory()->create();

    // Run 100 iterations with random salary structure data
    for ($i = 0; $i < 100; $i++) {
        // Generate random basic salary (IDR 1M - 100M as per design doc)
        $basicSalary = fake()->randomFloat(2, 1000000, 100000000);

        // Generate random effective date
        $effectiveDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        // Generate random fixed allowances (respecting 75:25 rule)
        // Max fixed allowances = basic_salary / 0.75 - basic_salary = basic_salary * 0.333
        $maxFixedTotal = $basicSalary * 0.333;
        $fixedAllowances = [];
        $allowanceTypes = ['position', 'family', 'housing', 'communication'];
        $numFixedAllowances = fake()->numberBetween(0, 3);
        $remainingFixed = $maxFixedTotal;

        for ($j = 0; $j < $numFixedAllowances && $remainingFixed > 0; $j++) {
            $amount = fake()->randomFloat(2, 100000, min($remainingFixed, 5000000));
            $remainingFixed -= $amount;
            $fixedAllowances[] = [
                'type' => $allowanceTypes[$j % count($allowanceTypes)],
                'name' => 'Tunjangan '.ucfirst($allowanceTypes[$j % count($allowanceTypes)]),
                'amount' => $amount,
            ];
        }

        // Generate random variable allowances
        $variableAllowances = [];
        $varTypes = ['transport', 'meal', 'overtime', 'performance'];
        $numVarAllowances = fake()->numberBetween(0, 4);

        for ($j = 0; $j < $numVarAllowances; $j++) {
            $variableAllowances[] = [
                'type' => $varTypes[$j % count($varTypes)],
                'name' => 'Tunjangan '.ucfirst($varTypes[$j % count($varTypes)]),
                'amount' => fake()->randomFloat(2, 100000, 3000000),
            ];
        }

        // Generate random deductions
        $deductions = [];
        $deductionTypes = ['loan', 'insurance', 'cooperative', 'other'];
        $numDeductions = fake()->numberBetween(0, 3);

        for ($j = 0; $j < $numDeductions; $j++) {
            $deductions[] = [
                'type' => $deductionTypes[$j % count($deductionTypes)],
                'name' => 'Potongan '.ucfirst($deductionTypes[$j % count($deductionTypes)]),
                'amount' => fake()->randomFloat(2, 50000, 1000000),
            ];
        }

        // Random PPh 21 method
        $pph21Method = fake()->randomElement(SalaryStructure::getPph21Methods());

        // Random JKK risk level
        $jkkRiskLevel = fake()->randomElement(SalaryStructure::getJkkRiskLevels());

        // Create salary structure
        $salaryStructure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => $effectiveDate,
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => $variableAllowances,
            'deductions' => $deductions,
            'pph21_method' => $pph21Method,
            'jkk_risk_level' => $jkkRiskLevel,
        ]);

        // Refresh from database to ensure we're testing persisted values
        $salaryStructure->refresh();

        // Property: employee_id must match (Requirement 1.1)
        expect($salaryStructure->employee_id)->toBe($employee->id);

        // Property: effective_date must match (Requirement 1.1)
        expect($salaryStructure->effective_date->format('Y-m-d'))->toBe($effectiveDate);

        // Property: basic_salary must match (Requirement 1.1)
        expect((float) $salaryStructure->basic_salary)->toEqualWithDelta(
            $basicSalary,
            0.01,
            'Basic salary was not stored correctly'
        );

        // Property: fixed allowances must match (Requirement 1.2)
        expect($salaryStructure->allowances_fixed)->toBeArray();
        expect(count($salaryStructure->allowances_fixed))->toBe(count($fixedAllowances));

        foreach ($fixedAllowances as $index => $allowance) {
            expect($salaryStructure->allowances_fixed[$index]['type'])->toBe($allowance['type']);
            expect($salaryStructure->allowances_fixed[$index]['name'])->toBe($allowance['name']);
            expect((float) $salaryStructure->allowances_fixed[$index]['amount'])->toEqualWithDelta(
                $allowance['amount'],
                0.01
            );
        }

        // Property: variable allowances must match (Requirement 1.2)
        expect($salaryStructure->allowances_variable)->toBeArray();
        expect(count($salaryStructure->allowances_variable))->toBe(count($variableAllowances));

        foreach ($variableAllowances as $index => $allowance) {
            expect($salaryStructure->allowances_variable[$index]['type'])->toBe($allowance['type']);
            expect($salaryStructure->allowances_variable[$index]['name'])->toBe($allowance['name']);
            expect((float) $salaryStructure->allowances_variable[$index]['amount'])->toEqualWithDelta(
                $allowance['amount'],
                0.01
            );
        }

        // Property: deductions must match (Requirement 1.3)
        expect($salaryStructure->deductions)->toBeArray();
        expect(count($salaryStructure->deductions))->toBe(count($deductions));

        foreach ($deductions as $index => $deduction) {
            expect($salaryStructure->deductions[$index]['type'])->toBe($deduction['type']);
            expect($salaryStructure->deductions[$index]['name'])->toBe($deduction['name']);
            expect((float) $salaryStructure->deductions[$index]['amount'])->toEqualWithDelta(
                $deduction['amount'],
                0.01
            );
        }

        // Property: PPh 21 method must match (Requirement 1.4)
        expect($salaryStructure->pph21_method)->toBe($pph21Method);

        // Property: JKK risk level must match
        expect($salaryStructure->jkk_risk_level)->toBe($jkkRiskLevel);

        // Clean up salary structure for next iteration
        $salaryStructure->forceDelete();
    }

    // Clean up employee at the end
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 3: Salary structure 75:25 rule validation**
 *
 * For any salary structure where basic_salary is less than 75% of
 * (basic_salary + total_fixed_allowances), the system SHALL reject
 * the structure with a validation error.
 *
 * **Validates: Requirements 1.8**
 */
test('Property 3: Salary structure 75:25 rule validation', function () {
    // Run 100 iterations with random salary structure data
    for ($i = 0; $i < 100; $i++) {
        // Generate random basic salary (IDR 1M - 100M)
        $basicSalary = fake()->randomFloat(2, 1000000, 100000000);

        // Randomly decide if we want to test a valid or invalid case
        $testValidCase = fake()->boolean();

        if ($testValidCase) {
            // Generate fixed allowances that satisfy the 75:25 rule
            // For basic_salary to be >= 75% of (basic + fixed), fixed must be <= basic * 0.333
            $maxFixedTotal = $basicSalary * 0.333;
            $totalFixedAmount = fake()->randomFloat(2, 0, $maxFixedTotal);
        } else {
            // Generate fixed allowances that violate the 75:25 rule
            // For basic_salary to be < 75% of (basic + fixed), fixed must be > basic * 0.333
            $minFixedTotal = $basicSalary * 0.334; // Just above the threshold
            $maxFixedTotal = $basicSalary * 2; // Up to 2x basic salary
            $totalFixedAmount = fake()->randomFloat(2, $minFixedTotal, $maxFixedTotal);
        }

        // Create fixed allowances array
        $fixedAllowances = [];
        if ($totalFixedAmount > 0) {
            $fixedAllowances[] = [
                'type' => 'position',
                'name' => 'Tunjangan Jabatan',
                'amount' => $totalFixedAmount,
            ];
        }

        // Create a salary structure instance (not persisted)
        $salaryStructure = new SalaryStructure([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => $fixedAllowances,
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);

        // Calculate expected validation result
        $basicPlusFixed = $basicSalary + $totalFixedAmount;
        $expectedRatio = $basicPlusFixed > 0 ? $basicSalary / $basicPlusFixed : 1;
        $expectedValid = $expectedRatio >= 0.75;

        // Property: validates7525Rule() must return correct result
        $actualValid = $salaryStructure->validates7525Rule();

        expect($actualValid)->toBe(
            $expectedValid,
            "75:25 rule validation failed for basic_salary={$basicSalary}, ".
            "fixed_allowances={$totalFixedAmount}, ratio=".number_format($expectedRatio * 100, 2).'%. '.
            'Expected: '.($expectedValid ? 'valid' : 'invalid').', '.
            'Got: '.($actualValid ? 'valid' : 'invalid')
        );

        // Additional property: total_fixed_allowances attribute must be calculated correctly
        expect($salaryStructure->total_fixed_allowances)->toEqualWithDelta(
            $totalFixedAmount,
            0.01,
            'Total fixed allowances calculation is incorrect'
        );

        // Property: When invalid, the error message should be informative
        if (! $expectedValid) {
            $message = $salaryStructure->get7525RuleMessage();
            expect($message)->toContain('75%');
            expect($message)->toContain('basic salary');
        }
    }
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 3 Edge Cases: 75:25 rule boundary conditions**
 *
 * Tests edge cases for the 75:25 rule validation including:
 * - Zero fixed allowances (always valid)
 * - Exactly 75% ratio (valid)
 * - Just below 75% ratio (invalid)
 * - Zero basic salary with zero fixed allowances
 *
 * **Validates: Requirements 1.8**
 */
test('Property 3 Edge Cases: 75:25 rule boundary conditions', function () {
    // Edge case 1: Zero fixed allowances should always be valid
    for ($i = 0; $i < 20; $i++) {
        $basicSalary = fake()->randomFloat(2, 1000000, 100000000);

        $salaryStructure = new SalaryStructure([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);

        expect($salaryStructure->validates7525Rule())->toBeTrue(
            'Zero fixed allowances should always pass 75:25 rule'
        );
    }

    // Edge case 2: Exactly 75% ratio (basic = 75% of total)
    // If basic = 75% of (basic + fixed), then fixed = basic / 3
    for ($i = 0; $i < 20; $i++) {
        $basicSalary = fake()->randomFloat(2, 3000000, 90000000);
        $fixedAllowances = $basicSalary / 3; // Exactly at 75% boundary

        $salaryStructure = new SalaryStructure([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [
                ['type' => 'position', 'name' => 'Test', 'amount' => $fixedAllowances],
            ],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);

        // At exactly 75%, should be valid (>= 0.75)
        expect($salaryStructure->validates7525Rule())->toBeTrue(
            'Exactly 75% ratio should be valid'
        );
    }

    // Edge case 3: Just below 75% ratio (should be invalid)
    for ($i = 0; $i < 20; $i++) {
        $basicSalary = fake()->randomFloat(2, 3000000, 90000000);
        // fixed = basic / 3 + small amount makes ratio < 75%
        $fixedAllowances = ($basicSalary / 3) + 1000;

        $salaryStructure = new SalaryStructure([
            'basic_salary' => $basicSalary,
            'allowances_fixed' => [
                ['type' => 'position', 'name' => 'Test', 'amount' => $fixedAllowances],
            ],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);

        expect($salaryStructure->validates7525Rule())->toBeFalse(
            'Just below 75% ratio should be invalid'
        );
    }

    // Edge case 4: Zero basic salary with zero fixed allowances
    $salaryStructure = new SalaryStructure([
        'basic_salary' => 0,
        'allowances_fixed' => [],
        'allowances_variable' => [],
        'deductions' => [],
        'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
    ]);

    // Should handle division by zero gracefully
    expect($salaryStructure->validates7525Rule())->toBeTrue(
        'Zero basic + zero fixed should be handled gracefully'
    );
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 2: Active salary structure selection**
 *
 * For any employee with multiple salary structures and a given payrun date,
 * the system SHALL return the structure with the most recent effective_date
 * that does not exceed the payrun date.
 *
 * **Validates: Requirements 1.6, 1.7**
 */
test('Property 2: Active salary structure selection', function () {
    // Run 100 iterations with random salary structure scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create a fresh employee for each iteration
        $employee = Employee::factory()->create();

        // Generate a random payrun date
        $payrunDate = \Carbon\Carbon::instance(fake()->dateTimeBetween('-2 years', 'now'));

        // Generate random number of salary structures (1-10)
        $numStructures = fake()->numberBetween(1, 10);

        // Create salary structures with various effective dates
        $structures = [];
        $effectiveDates = [];

        for ($j = 0; $j < $numStructures; $j++) {
            // Generate effective dates: some before payrun, some after
            $daysOffset = fake()->numberBetween(-730, 730); // +/- 2 years
            $effectiveDate = (clone $payrunDate)->addDays($daysOffset);

            $structure = SalaryStructure::create([
                'employee_id' => $employee->id,
                'effective_date' => $effectiveDate,
                'basic_salary' => fake()->randomFloat(2, 1000000, 100000000),
                'allowances_fixed' => [],
                'allowances_variable' => [],
                'deductions' => [],
                'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
                'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
            ]);

            $structures[] = $structure;
            $effectiveDates[] = $effectiveDate;
        }

        // Find the expected active effective date manually.
        // Ties on the same effective_date are valid; only recency is defined.
        $expectedEffectiveDate = null;

        foreach ($structures as $structure) {
            if ($structure->effective_date <= $payrunDate) {
                if ($expectedEffectiveDate === null || $structure->effective_date > $expectedEffectiveDate) {
                    $expectedEffectiveDate = $structure->effective_date;
                }
            }
        }

        // Get the active structure using the service method
        $payrollService = app(\App\Services\Hris\PayrollCalculationService::class);
        $activeStructure = $payrollService->getActiveSalaryStructure(
            $employee,
            \Carbon\Carbon::instance($payrunDate)
        );

        // Property: If there's a valid structure, it must be the expected one
        if ($expectedEffectiveDate !== null) {
            expect($activeStructure)->not->toBeNull(
                "Should find an active salary structure for payrun date {$payrunDate->format('Y-m-d')}"
            );

            expect($activeStructure->effective_date->toDateString())->toBe(
                $expectedEffectiveDate->toDateString(),
                'Active structure should be the most recent one with effective_date <= payrun date. '.
                "Expected: {$expectedEffectiveDate->format('Y-m-d')}, ".
                "Got: {$activeStructure->effective_date->format('Y-m-d')}"
            );

            // Property: The active structure's effective_date must not exceed payrun date
            expect($activeStructure->effective_date)->toBeLessThanOrEqual(
                \Carbon\Carbon::instance($payrunDate),
                'Active structure effective_date must not exceed payrun date'
            );

            // Property: No other structure with earlier effective_date should be selected
            foreach ($structures as $structure) {
                if ($structure->id !== $activeStructure->id && $structure->effective_date <= $payrunDate) {
                    expect($structure->effective_date)->toBeLessThanOrEqual(
                        $activeStructure->effective_date,
                        'Selected structure should have the most recent effective_date'
                    );
                }
            }
        } else {
            // If no valid structure exists, the method should return null
            expect($activeStructure)->toBeNull(
                'Should return null when no salary structure has effective_date <= payrun date'
            );
        }

        // Clean up
        foreach ($structures as $structure) {
            $structure->forceDelete();
        }
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
})->group('property-test', 'hris-payroll');

/**
 * **Feature: hris-phase3-payroll, Property 2 Edge Cases: Active salary structure boundary conditions**
 *
 * Tests edge cases for active salary structure selection:
 * - No structures exist (should return null)
 * - All structures are in the future (should return null)
 * - All structures are in the past (should return most recent)
 * - Structure effective_date equals payrun date (should be selected)
 * - Multiple structures on same date (should return one of them)
 *
 * **Validates: Requirements 1.6, 1.7**
 */
test('Property 2 Edge Cases: Active salary structure boundary conditions', function () {
    // Edge case 1: No structures exist
    $employee = Employee::factory()->create();
    $payrunDate = now();

    $payrollService = app(\App\Services\Hris\PayrollCalculationService::class);
    $activeStructure = $payrollService->getActiveSalaryStructure($employee, $payrunDate);

    expect($activeStructure)->toBeNull(
        'Should return null when no salary structures exist'
    );

    // Edge case 2: All structures are in the future
    for ($i = 0; $i < 5; $i++) {
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now()->addDays(fake()->numberBetween(1, 365)),
            'basic_salary' => fake()->randomFloat(2, 1000000, 100000000),
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);
    }

    $activeStructure = $payrollService->getActiveSalaryStructure($employee, $payrunDate);

    expect($activeStructure)->toBeNull(
        'Should return null when all structures have future effective_dates'
    );

    // Clean up for next edge case
    SalaryStructure::where('employee_id', $employee->id)->forceDelete();

    // Edge case 3: All structures are in the past
    $pastStructures = [];
    for ($i = 0; $i < 5; $i++) {
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now()->subDays(fake()->numberBetween(1, 365)),
            'basic_salary' => fake()->randomFloat(2, 1000000, 100000000),
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
            'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
        ]);
        $pastStructures[] = $structure;
    }

    $activeStructure = $payrollService->getActiveSalaryStructure($employee, $payrunDate);

    expect($activeStructure)->not->toBeNull(
        'Should return a structure when all are in the past'
    );

    // Should be the most recent one
    $mostRecentPast = collect($pastStructures)->sortByDesc('effective_date')->first();
    expect($activeStructure->id)->toBe(
        $mostRecentPast->id,
        'Should return the most recent past structure'
    );

    // Clean up for next edge case
    SalaryStructure::where('employee_id', $employee->id)->forceDelete();

    // Edge case 4: Structure effective_date equals payrun date
    $exactDate = now();
    $structure = SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => $exactDate,
        'basic_salary' => fake()->randomFloat(2, 1000000, 100000000),
        'allowances_fixed' => [],
        'allowances_variable' => [],
        'deductions' => [],
        'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
    ]);

    $activeStructure = $payrollService->getActiveSalaryStructure($employee, $exactDate);

    expect($activeStructure)->not->toBeNull(
        'Should return structure when effective_date equals payrun date'
    );
    expect($activeStructure->id)->toBe(
        $structure->id,
        'Should return the structure with matching effective_date'
    );

    // Clean up for next edge case
    SalaryStructure::where('employee_id', $employee->id)->forceDelete();

    // Edge case 5: Multiple structures on same date (should return one)
    $sameDate = now()->subDays(30);
    $structure1 = SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => $sameDate,
        'basic_salary' => 5000000,
        'allowances_fixed' => [],
        'allowances_variable' => [],
        'deductions' => [],
        'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
    ]);

    $structure2 = SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => $sameDate,
        'basic_salary' => 6000000,
        'allowances_fixed' => [],
        'allowances_variable' => [],
        'deductions' => [],
        'pph21_method' => SalaryStructure::PPH21_METHOD_GROSS,
        'jkk_risk_level' => SalaryStructure::JKK_RISK_LOW,
    ]);

    $activeStructure = $payrollService->getActiveSalaryStructure($employee, now());

    expect($activeStructure)->not->toBeNull(
        'Should return a structure when multiple exist on same date'
    );
    expect(in_array($activeStructure->id, [$structure1->id, $structure2->id]))->toBeTrue(
        'Should return one of the structures with the same effective_date'
    );

    // Clean up
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-payroll');
