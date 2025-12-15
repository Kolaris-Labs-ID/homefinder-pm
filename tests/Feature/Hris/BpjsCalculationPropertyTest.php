<?php

/**
 * Property-Based Tests for BpjsCalculationService
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Services\Hris\BpjsCalculationService;

/**
 * **Feature: hris-phase3-payroll, Property 9: BPJS Kesehatan calculation with ceiling**
 *
 * For any salary, BPJS Kesehatan employee contribution SHALL be 1% of
 * min(salary, 12,000,000) and company contribution SHALL be 4% of
 * min(salary, 12,000,000).
 *
 * **Validates: Requirements 3.2**
 */
test('Property 9: BPJS Kesehatan calculation with ceiling', function () {
    $service = new BpjsCalculationService();
    $ceiling = BpjsCalculationService::KESEHATAN_CEILING; // 12,000,000

    // Run 100 iterations with random salary data
    for ($i = 0; $i < 100; $i++) {
        // Generate random salary (IDR 1M - 100M as per design doc)
        $salary = fake()->randomFloat(2, 1000000, 100000000);

        // Calculate using the service
        $result = $service->calculateKesehatan($salary);

        // Calculate expected values based on the property definition
        $cappedSalary = min($salary, $ceiling);
        $expectedEmployee = round($cappedSalary * 0.01, 2); // 1%
        $expectedCompany = round($cappedSalary * 0.04, 2);   // 4%

        // Property: Employee contribution SHALL be 1% of min(salary, ceiling)
        expect($result['employee'])->toEqualWithDelta(
            $expectedEmployee,
            0.01,
            "Employee contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedEmployee}, Got: {$result['employee']}"
        );

        // Property: Company contribution SHALL be 4% of min(salary, ceiling)
        expect($result['company'])->toEqualWithDelta(
            $expectedCompany,
            0.01,
            "Company contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedCompany}, Got: {$result['company']}"
        );

        // Property: When salary exceeds ceiling, contributions should be capped
        if ($salary > $ceiling) {
            $maxEmployee = round($ceiling * 0.01, 2);
            $maxCompany = round($ceiling * 0.04, 2);

            expect($result['employee'])->toBeLessThanOrEqual(
                $maxEmployee + 0.01,
                "Employee contribution should be capped at ceiling"
            );
            expect($result['company'])->toBeLessThanOrEqual(
                $maxCompany + 0.01,
                "Company contribution should be capped at ceiling"
            );
        }
    }
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 9 Edge Cases: BPJS Kesehatan boundary conditions**
 *
 * Tests edge cases for BPJS Kesehatan calculation including:
 * - Salary exactly at ceiling
 * - Salary just below ceiling
 * - Salary just above ceiling
 * - Minimum salary
 *
 * **Validates: Requirements 3.2**
 */
test('Property 9 Edge Cases: BPJS Kesehatan boundary conditions', function () {
    $service = new BpjsCalculationService();
    $ceiling = BpjsCalculationService::KESEHATAN_CEILING; // 12,000,000

    // Edge case 1: Salary exactly at ceiling
    $result = $service->calculateKesehatan($ceiling);
    expect($result['employee'])->toEqualWithDelta(120000, 0.01); // 1% of 12M
    expect($result['company'])->toEqualWithDelta(480000, 0.01);  // 4% of 12M

    // Edge case 2: Salary just below ceiling
    $salaryBelowCeiling = $ceiling - 1;
    $result = $service->calculateKesehatan($salaryBelowCeiling);
    expect($result['employee'])->toEqualWithDelta(round($salaryBelowCeiling * 0.01, 2), 0.01);
    expect($result['company'])->toEqualWithDelta(round($salaryBelowCeiling * 0.04, 2), 0.01);

    // Edge case 3: Salary just above ceiling
    $salaryAboveCeiling = $ceiling + 1;
    $result = $service->calculateKesehatan($salaryAboveCeiling);
    // Should be capped at ceiling values
    expect($result['employee'])->toEqualWithDelta(120000, 0.01);
    expect($result['company'])->toEqualWithDelta(480000, 0.01);

    // Edge case 4: Very high salary (should still be capped)
    $veryHighSalary = 100000000; // 100M
    $result = $service->calculateKesehatan($veryHighSalary);
    expect($result['employee'])->toEqualWithDelta(120000, 0.01);
    expect($result['company'])->toEqualWithDelta(480000, 0.01);

    // Edge case 5: Minimum wage scenario
    $minimumSalary = 1000000; // 1M
    $result = $service->calculateKesehatan($minimumSalary);
    expect($result['employee'])->toEqualWithDelta(10000, 0.01);  // 1% of 1M
    expect($result['company'])->toEqualWithDelta(40000, 0.01);   // 4% of 1M

    // Edge case 6: Zero salary
    $result = $service->calculateKesehatan(0);
    expect($result['employee'])->toBe(0.0);
    expect($result['company'])->toBe(0.0);
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 10: BPJS JHT calculation without ceiling**
 *
 * For any salary, BPJS JHT employee contribution SHALL be 2% of salary and
 * company contribution SHALL be 3.7% of salary.
 *
 * **Validates: Requirements 3.3**
 */
test('Property 10: BPJS JHT calculation without ceiling', function () {
    $service = new BpjsCalculationService();

    // Run 100 iterations with random salary data
    for ($i = 0; $i < 100; $i++) {
        // Generate random salary (IDR 1M - 100M as per design doc)
        $salary = fake()->randomFloat(2, 1000000, 100000000);

        // Calculate using the service
        $result = $service->calculateJht($salary);

        // Calculate expected values based on the property definition
        // JHT has NO ceiling, so we apply rates directly to salary
        $expectedEmployee = round($salary * 0.02, 2);   // 2%
        $expectedCompany = round($salary * 0.037, 2);   // 3.7%

        // Property: Employee contribution SHALL be 2% of salary (no ceiling)
        expect($result['employee'])->toEqualWithDelta(
            $expectedEmployee,
            0.01,
            "Employee contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedEmployee}, Got: {$result['employee']}"
        );

        // Property: Company contribution SHALL be 3.7% of salary (no ceiling)
        expect($result['company'])->toEqualWithDelta(
            $expectedCompany,
            0.01,
            "Company contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedCompany}, Got: {$result['company']}"
        );

        // Property: Unlike Kesehatan and JP, JHT should NOT be capped
        // Even for very high salaries, the percentage should apply fully
        $minExpectedEmployee = round(1000000 * 0.02, 2);
        $maxExpectedEmployee = round(100000000 * 0.02, 2);

        expect($result['employee'])->toBeGreaterThanOrEqual($minExpectedEmployee - 1);
        expect($result['employee'])->toBeLessThanOrEqual($maxExpectedEmployee + 1);
    }
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 10 Edge Cases: BPJS JHT boundary conditions**
 *
 * Tests edge cases for BPJS JHT calculation including:
 * - Very high salary (should NOT be capped unlike Kesehatan)
 * - Minimum salary
 * - Zero salary
 * - Salary at various scales
 *
 * **Validates: Requirements 3.3**
 */
test('Property 10 Edge Cases: BPJS JHT boundary conditions', function () {
    $service = new BpjsCalculationService();

    // Edge case 1: Very high salary (should NOT be capped)
    $veryHighSalary = 100000000; // 100M
    $result = $service->calculateJht($veryHighSalary);
    expect($result['employee'])->toEqualWithDelta(2000000, 0.01);    // 2% of 100M
    expect($result['company'])->toEqualWithDelta(3700000, 0.01);     // 3.7% of 100M

    // Edge case 2: Salary above Kesehatan ceiling (should still apply full rate)
    $aboveKesehatan = 15000000; // 15M (above 12M Kesehatan ceiling)
    $result = $service->calculateJht($aboveKesehatan);
    expect($result['employee'])->toEqualWithDelta(300000, 0.01);     // 2% of 15M
    expect($result['company'])->toEqualWithDelta(555000, 0.01);      // 3.7% of 15M

    // Edge case 3: Minimum wage scenario
    $minimumSalary = 1000000; // 1M
    $result = $service->calculateJht($minimumSalary);
    expect($result['employee'])->toEqualWithDelta(20000, 0.01);      // 2% of 1M
    expect($result['company'])->toEqualWithDelta(37000, 0.01);       // 3.7% of 1M

    // Edge case 4: Zero salary
    $result = $service->calculateJht(0);
    expect($result['employee'])->toBe(0.0);
    expect($result['company'])->toBe(0.0);

    // Edge case 5: Salary at JP ceiling (should still apply full rate)
    $jpCeiling = 10547400;
    $result = $service->calculateJht($jpCeiling);
    expect($result['employee'])->toEqualWithDelta(round($jpCeiling * 0.02, 2), 0.01);
    expect($result['company'])->toEqualWithDelta(round($jpCeiling * 0.037, 2), 0.01);

    // Edge case 6: Salary just above JP ceiling
    $aboveJpCeiling = 10547401;
    $result = $service->calculateJht($aboveJpCeiling);
    expect($result['employee'])->toEqualWithDelta(round($aboveJpCeiling * 0.02, 2), 0.01);
    expect($result['company'])->toEqualWithDelta(round($aboveJpCeiling * 0.037, 2), 0.01);
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 11: BPJS JP calculation with ceiling**
 *
 * For any salary, BPJS JP employee contribution SHALL be 1% of
 * min(salary, 10,547,400) and company contribution SHALL be 2% of
 * min(salary, 10,547,400).
 *
 * **Validates: Requirements 3.4**
 */
test('Property 11: BPJS JP calculation with ceiling', function () {
    $service = new BpjsCalculationService();
    $ceiling = BpjsCalculationService::JP_CEILING; // 10,547,400

    // Run 100 iterations with random salary data
    for ($i = 0; $i < 100; $i++) {
        // Generate random salary (IDR 1M - 100M as per design doc)
        $salary = fake()->randomFloat(2, 1000000, 100000000);

        // Calculate using the service
        $result = $service->calculateJp($salary);

        // Calculate expected values based on the property definition
        $cappedSalary = min($salary, $ceiling);
        $expectedEmployee = round($cappedSalary * 0.01, 2); // 1%
        $expectedCompany = round($cappedSalary * 0.02, 2);  // 2%

        // Property: Employee contribution SHALL be 1% of min(salary, ceiling)
        expect($result['employee'])->toEqualWithDelta(
            $expectedEmployee,
            0.01,
            "Employee contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedEmployee}, Got: {$result['employee']}"
        );

        // Property: Company contribution SHALL be 2% of min(salary, ceiling)
        expect($result['company'])->toEqualWithDelta(
            $expectedCompany,
            0.01,
            "Company contribution incorrect for salary={$salary}. " .
            "Expected: {$expectedCompany}, Got: {$result['company']}"
        );

        // Property: When salary exceeds ceiling, contributions should be capped
        if ($salary > $ceiling) {
            $maxEmployee = round($ceiling * 0.01, 2);
            $maxCompany = round($ceiling * 0.02, 2);

            expect($result['employee'])->toBeLessThanOrEqual(
                $maxEmployee + 0.01,
                "Employee contribution should be capped at ceiling"
            );
            expect($result['company'])->toBeLessThanOrEqual(
                $maxCompany + 0.01,
                "Company contribution should be capped at ceiling"
            );
        }
    }
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 11 Edge Cases: BPJS JP boundary conditions**
 *
 * Tests edge cases for BPJS JP calculation including:
 * - Salary exactly at ceiling
 * - Salary just below ceiling
 * - Salary just above ceiling
 * - Minimum salary
 * - Zero salary
 *
 * **Validates: Requirements 3.4**
 */
test('Property 11 Edge Cases: BPJS JP boundary conditions', function () {
    $service = new BpjsCalculationService();
    $ceiling = BpjsCalculationService::JP_CEILING; // 10,547,400

    // Edge case 1: Salary exactly at ceiling
    $result = $service->calculateJp($ceiling);
    expect($result['employee'])->toEqualWithDelta(105474, 0.01);    // 1% of 10,547,400
    expect($result['company'])->toEqualWithDelta(210948, 0.01);     // 2% of 10,547,400

    // Edge case 2: Salary just below ceiling
    $salaryBelowCeiling = $ceiling - 1;
    $result = $service->calculateJp($salaryBelowCeiling);
    expect($result['employee'])->toEqualWithDelta(round($salaryBelowCeiling * 0.01, 2), 0.01);
    expect($result['company'])->toEqualWithDelta(round($salaryBelowCeiling * 0.02, 2), 0.01);

    // Edge case 3: Salary just above ceiling
    $salaryAboveCeiling = $ceiling + 1;
    $result = $service->calculateJp($salaryAboveCeiling);
    // Should be capped at ceiling values
    expect($result['employee'])->toEqualWithDelta(105474, 0.01);
    expect($result['company'])->toEqualWithDelta(210948, 0.01);

    // Edge case 4: Very high salary (should still be capped)
    $veryHighSalary = 100000000; // 100M
    $result = $service->calculateJp($veryHighSalary);
    expect($result['employee'])->toEqualWithDelta(105474, 0.01);
    expect($result['company'])->toEqualWithDelta(210948, 0.01);

    // Edge case 5: Minimum wage scenario
    $minimumSalary = 1000000; // 1M
    $result = $service->calculateJp($minimumSalary);
    expect($result['employee'])->toEqualWithDelta(10000, 0.01);     // 1% of 1M
    expect($result['company'])->toEqualWithDelta(20000, 0.01);      // 2% of 1M

    // Edge case 6: Zero salary
    $result = $service->calculateJp(0);
    expect($result['employee'])->toBe(0.0);
    expect($result['company'])->toBe(0.0);

    // Edge case 7: Salary between Kesehatan and JP ceiling
    $betweenCeilings = 11000000; // Between Kesehatan (12M) and JP (10.5M) - above JP
    $result = $service->calculateJp($betweenCeilings);
    // Should be capped at JP ceiling
    expect($result['employee'])->toEqualWithDelta(105474, 0.01);
    expect($result['company'])->toEqualWithDelta(210948, 0.01);
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 12: BPJS JKK and JKM calculation**
 *
 * For any salary and JKK risk level, JKK contribution SHALL be the risk-level
 * rate applied to salary (company only), and JKM SHALL be 0.30% of salary
 * (company only).
 *
 * **Validates: Requirements 3.5, 3.6**
 */
test('Property 12: BPJS JKK and JKM calculation', function () {
    $service = new BpjsCalculationService();
    $jkkRiskLevels = BpjsCalculationService::getJkkRiskLevels();
    $jkkRates = BpjsCalculationService::getJkkRatesMapping();

    // Run 100 iterations with random salary data
    for ($i = 0; $i < 100; $i++) {
        // Generate random salary (IDR 1M - 100M as per design doc)
        $salary = fake()->randomFloat(2, 1000000, 100000000);

        // Test each JKK risk level
        foreach ($jkkRiskLevels as $riskLevel) {
            // Calculate JKK using the service
            $jkkResult = $service->calculateJkk($salary, $riskLevel);

            // Calculate JKM using the service
            $jkmResult = $service->calculateJkm($salary);

            // Calculate expected values based on the property definition
            $expectedJkk = round($salary * $jkkRates[$riskLevel], 2);
            $expectedJkm = round($salary * 0.003, 2); // 0.30%

            // Property: JKK contribution SHALL be risk-level rate applied to salary
            expect($jkkResult)->toEqualWithDelta(
                $expectedJkk,
                0.01,
                "JKK contribution incorrect for salary={$salary}, risk_level={$riskLevel}. " .
                "Expected: {$expectedJkk}, Got: {$jkkResult}"
            );

            // Property: JKM contribution SHALL be 0.30% of salary
            expect($jkmResult)->toEqualWithDelta(
                $expectedJkm,
                0.01,
                "JKM contribution incorrect for salary={$salary}. " .
                "Expected: {$expectedJkm}, Got: {$jkmResult}"
            );

            // Property: Both JKK and JKM are company-only (no ceiling)
            // They should scale linearly with salary
            if ($salary > 0) {
                expect($jkkResult)->toBeGreaterThanOrEqual(0);
                expect($jkmResult)->toBeGreaterThanOrEqual(0);
            }
        }
    }
})->group('property-test', 'hris-payroll', 'bpjs');

/**
 * **Feature: hris-phase3-payroll, Property 12 Edge Cases: BPJS JKK and JKM boundary conditions**
 *
 * Tests edge cases for BPJS JKK and JKM calculation including:
 * - All JKK risk levels (very_low, low, medium, high, very_high)
 * - Salary at various scales
 * - Zero salary
 * - Very high salary (should NOT be capped)
 *
 * **Validates: Requirements 3.5, 3.6**
 */
test('Property 12 Edge Cases: BPJS JKK and JKM boundary conditions', function () {
    $service = new BpjsCalculationService();

    // Test all JKK risk levels with a standard salary
    $testSalary = 10000000; // 10M for easy calculation

    // Edge case 1: Very low risk
    $result = $service->calculateJkk($testSalary, 'very_low');
    expect($result)->toEqualWithDelta(24000, 0.01); // 0.24% of 10M

    // Edge case 2: Low risk
    $result = $service->calculateJkk($testSalary, 'low');
    expect($result)->toEqualWithDelta(54000, 0.01); // 0.54% of 10M

    // Edge case 3: Medium risk
    $result = $service->calculateJkk($testSalary, 'medium');
    expect($result)->toEqualWithDelta(89000, 0.01); // 0.89% of 10M

    // Edge case 4: High risk
    $result = $service->calculateJkk($testSalary, 'high');
    expect($result)->toEqualWithDelta(127000, 0.01); // 1.27% of 10M

    // Edge case 5: Very high risk
    $result = $service->calculateJkk($testSalary, 'very_high');
    expect($result)->toEqualWithDelta(174000, 0.01); // 1.74% of 10M

    // Edge case 6: JKM with standard salary
    $result = $service->calculateJkm($testSalary);
    expect($result)->toEqualWithDelta(30000, 0.01); // 0.30% of 10M

    // Edge case 7: Very high salary (should NOT be capped for JKK/JKM)
    $veryHighSalary = 100000000; // 100M
    $result = $service->calculateJkk($veryHighSalary, 'low');
    expect($result)->toEqualWithDelta(540000, 0.01); // 0.54% of 100M

    $result = $service->calculateJkm($veryHighSalary);
    expect($result)->toEqualWithDelta(300000, 0.01); // 0.30% of 100M

    // Edge case 8: Minimum wage scenario
    $minimumSalary = 1000000; // 1M
    $result = $service->calculateJkk($minimumSalary, 'low');
    expect($result)->toEqualWithDelta(5400, 0.01); // 0.54% of 1M

    $result = $service->calculateJkm($minimumSalary);
    expect($result)->toEqualWithDelta(3000, 0.01); // 0.30% of 1M

    // Edge case 9: Zero salary
    $result = $service->calculateJkk(0, 'low');
    expect($result)->toBe(0.0);

    $result = $service->calculateJkm(0);
    expect($result)->toBe(0.0);

    // Edge case 10: Salary above Kesehatan ceiling (should still apply full rate)
    $aboveKesehatan = 15000000; // 15M (above 12M Kesehatan ceiling)
    $result = $service->calculateJkk($aboveKesehatan, 'medium');
    expect($result)->toEqualWithDelta(133500, 0.01); // 0.89% of 15M

    $result = $service->calculateJkm($aboveKesehatan);
    expect($result)->toEqualWithDelta(45000, 0.01); // 0.30% of 15M

    // Edge case 11: Salary above JP ceiling (should still apply full rate)
    $aboveJpCeiling = 11000000; // 11M (above 10.547M JP ceiling)
    $result = $service->calculateJkk($aboveJpCeiling, 'high');
    expect($result)->toEqualWithDelta(139700, 0.01); // 1.27% of 11M

    $result = $service->calculateJkm($aboveJpCeiling);
    expect($result)->toEqualWithDelta(33000, 0.01); // 0.30% of 11M

    // Edge case 12: Default risk level (should be 'low')
    $result = $service->calculateJkk($testSalary); // No risk level specified
    expect($result)->toEqualWithDelta(54000, 0.01); // 0.54% of 10M (low risk)

    // Edge case 13: Invalid risk level (should default to 'low')
    $result = $service->calculateJkk($testSalary, 'invalid_level');
    expect($result)->toEqualWithDelta(54000, 0.01); // 0.54% of 10M (low risk default)
})->group('property-test', 'hris-payroll', 'bpjs');
