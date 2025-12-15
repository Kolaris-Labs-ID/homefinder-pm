<?php

/**
 * Property-Based Tests for JKK Risk Level to Rate Mapping
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach.
 */

use App\Services\Hris\BpjsCalculationService;

/**
 * **Feature: hris-phase3-payroll, Property 4: JKK risk level to rate mapping**
 *
 * For any JKK risk level (very_low, low, medium, high, very_high), the system
 * SHALL return the correct rate (0.24%, 0.54%, 0.89%, 1.27%, 1.74% respectively).
 *
 * **Validates: Requirements 1.9**
 */
test('Property 4: JKK risk level to rate mapping', function () {
    $service = new BpjsCalculationService();

    // Define the expected mapping as per Requirements 1.9
    $expectedRates = [
        'very_low' => 0.0024,   // 0.24%
        'low' => 0.0054,        // 0.54%
        'medium' => 0.0089,     // 0.89%
        'high' => 0.0127,       // 1.27%
        'very_high' => 0.0174,  // 1.74%
    ];

    // Run 100 iterations with random salary data to verify the mapping holds
    for ($i = 0; $i < 100; $i++) {
        // Generate random salary (IDR 1M - 100M as per design doc)
        $salary = fake()->randomFloat(2, 1000000, 100000000);

        // For each risk level, verify the rate mapping is correct
        foreach ($expectedRates as $riskLevel => $expectedRate) {
            // Get the rate from the service
            $actualRate = $service->getJkkRate($riskLevel);

            // Property: The rate SHALL match the expected rate for this risk level
            expect($actualRate)->toBe(
                $expectedRate,
                "JKK rate mapping incorrect for risk_level={$riskLevel}. " .
                "Expected: {$expectedRate}, Got: {$actualRate}"
            );

            // Verify the calculation uses the correct rate
            $jkkContribution = $service->calculateJkk($salary, $riskLevel);
            $expectedContribution = round($salary * $expectedRate, 2);

            expect($jkkContribution)->toEqualWithDelta(
                $expectedContribution,
                0.01,
                "JKK calculation incorrect for salary={$salary}, risk_level={$riskLevel}. " .
                "Expected: {$expectedContribution}, Got: {$jkkContribution}"
            );
        }
    }
})->group('property-test', 'hris-payroll', 'bpjs', 'jkk');

/**
 * **Feature: hris-phase3-payroll, Property 4 Edge Cases: JKK risk level mapping completeness**
 *
 * Tests that:
 * - All five risk levels are defined
 * - Each risk level maps to exactly one rate
 * - The rates are in ascending order by risk level
 * - Invalid risk levels default to 'low' rate
 *
 * **Validates: Requirements 1.9**
 */
test('Property 4 Edge Cases: JKK risk level mapping completeness', function () {
    $service = new BpjsCalculationService();

    // Expected rates as per Requirements 1.9
    $expectedRates = [
        'very_low' => 0.0024,   // 0.24%
        'low' => 0.0054,        // 0.54%
        'medium' => 0.0089,     // 0.89%
        'high' => 0.0127,       // 1.27%
        'very_high' => 0.0174,  // 1.74%
    ];

    // Edge case 1: Verify all five risk levels are available
    $availableLevels = BpjsCalculationService::getJkkRiskLevels();
    expect($availableLevels)->toHaveCount(5);
    expect($availableLevels)->toContain('very_low');
    expect($availableLevels)->toContain('low');
    expect($availableLevels)->toContain('medium');
    expect($availableLevels)->toContain('high');
    expect($availableLevels)->toContain('very_high');

    // Edge case 2: Verify the rates mapping is complete
    $ratesMapping = BpjsCalculationService::getJkkRatesMapping();
    expect($ratesMapping)->toHaveCount(5);
    expect($ratesMapping)->toBe($expectedRates);

    // Edge case 3: Verify rates are in ascending order by risk level
    $rateValues = array_values($expectedRates);
    for ($i = 1; $i < count($rateValues); $i++) {
        expect($rateValues[$i])->toBeGreaterThan(
            $rateValues[$i - 1],
            "JKK rates should be in ascending order by risk level"
        );
    }

    // Edge case 4: Verify each rate is within valid range (0% to 2%)
    foreach ($expectedRates as $riskLevel => $rate) {
        expect($rate)->toBeGreaterThan(0, "Rate for {$riskLevel} should be positive");
        expect($rate)->toBeLessThan(0.02, "Rate for {$riskLevel} should be less than 2%");
    }

    // Edge case 5: Verify invalid risk level defaults to 'low'
    $invalidLevelRate = $service->getJkkRate('invalid_level');
    expect($invalidLevelRate)->toBe($expectedRates['low']);

    // Edge case 6: Verify empty string defaults to 'low'
    $emptyLevelRate = $service->getJkkRate('');
    expect($emptyLevelRate)->toBe($expectedRates['low']);

    // Edge case 7: Verify calculation with invalid level uses 'low' rate
    $testSalary = 10000000; // 10M
    $invalidLevelContribution = $service->calculateJkk($testSalary, 'nonexistent');
    $lowLevelContribution = $service->calculateJkk($testSalary, 'low');
    expect($invalidLevelContribution)->toBe($lowLevelContribution);
})->group('property-test', 'hris-payroll', 'bpjs', 'jkk');

/**
 * **Feature: hris-phase3-payroll, Property 4 Verification: JKK rate percentage accuracy**
 *
 * Verifies that the decimal rates correctly represent the percentage values
 * specified in Requirements 1.9.
 *
 * **Validates: Requirements 1.9**
 */
test('Property 4 Verification: JKK rate percentage accuracy', function () {
    // Verify the decimal rates match the percentage specifications
    $ratesMapping = BpjsCalculationService::getJkkRatesMapping();

    // very_low: 0.24%
    expect($ratesMapping['very_low'] * 100)->toEqualWithDelta(0.24, 0.001);

    // low: 0.54%
    expect($ratesMapping['low'] * 100)->toEqualWithDelta(0.54, 0.001);

    // medium: 0.89%
    expect($ratesMapping['medium'] * 100)->toEqualWithDelta(0.89, 0.001);

    // high: 1.27%
    expect($ratesMapping['high'] * 100)->toEqualWithDelta(1.27, 0.001);

    // very_high: 1.74%
    expect($ratesMapping['very_high'] * 100)->toEqualWithDelta(1.74, 0.001);
})->group('property-test', 'hris-payroll', 'bpjs', 'jkk');
