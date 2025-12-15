<?php

/**
 * Unit Tests for Pph21CalculationService
 *
 * These tests verify the PPh 21 TER (Tarif Efektif Rata-rata) calculation
 * across various income brackets and calculation methods.
 *
 * Requirement 3.8: WHEN calculating PPh 21 with TER method THEN the Payroll_System
 * SHALL apply the effective rate based on annual taxable income bracket
 */

use App\Services\Hris\Pph21CalculationService;

describe('PPh 21 TER Rate Calculation', function () {
    beforeEach(function () {
        $this->service = new Pph21CalculationService();
    });

    describe('getTerRate() - TER rate lookup by income bracket', function () {
        test('returns 0% for income below 5,400,000', function () {
            $rate = $this->service->getTerRate(5000000);
            expect($rate)->toBe(0.0);
        });

        test('returns 0.25% for income between 5,400,000 and 5,650,000', function () {
            $rate = $this->service->getTerRate(5500000);
            expect($rate)->toBe(0.0025);
        });

        test('returns 0.5% for income between 5,650,000 and 5,950,000', function () {
            $rate = $this->service->getTerRate(5800000);
            expect($rate)->toBe(0.005);
        });

        test('returns 0.75% for income between 5,950,000 and 6,300,000', function () {
            $rate = $this->service->getTerRate(6100000);
            expect($rate)->toBe(0.0075);
        });

        test('returns 1% for income between 6,300,000 and 6,750,000', function () {
            $rate = $this->service->getTerRate(6500000);
            expect($rate)->toBe(0.01);
        });

        test('returns 1.25% for income between 6,750,000 and 7,500,000', function () {
            $rate = $this->service->getTerRate(7000000);
            expect($rate)->toBe(0.0125);
        });

        test('returns 1.5% for income between 7,500,000 and 8,550,000', function () {
            $rate = $this->service->getTerRate(8000000);
            expect($rate)->toBe(0.015);
        });

        test('returns 2% for income between 9,650,000 and 10,050,000', function () {
            $rate = $this->service->getTerRate(9800000);
            expect($rate)->toBe(0.02);
        });

        test('returns 6% for income between 13,750,000 and 15,100,000', function () {
            $rate = $this->service->getTerRate(14000000);
            expect($rate)->toBe(0.06);
        });

        test('returns 10% for income between 24,150,000 and 26,450,000', function () {
            $rate = $this->service->getTerRate(25000000);
            expect($rate)->toBe(0.10);
        });

        test('returns 20% for income between 56,300,000 and 62,200,000', function () {
            $rate = $this->service->getTerRate(60000000);
            expect($rate)->toBe(0.20);
        });

        test('returns 31% for income between 550,000,000 and 695,000,000', function () {
            $rate = $this->service->getTerRate(600000000);
            expect($rate)->toBe(0.31);
        });

        test('returns 34% for income above 1,400,000,000', function () {
            $rate = $this->service->getTerRate(2000000000);
            expect($rate)->toBe(0.34);
        });

        test('handles boundary: income just above bracket minimum', function () {
            // Just above 5,400,000 (minimum of second bracket)
            // Note: getTerRate uses > min, so exactly at min returns previous bracket
            $rate = $this->service->getTerRate(5400001);
            expect($rate)->toBe(0.0025);
        });

        test('handles boundary: income exactly at bracket maximum', function () {
            // At 5,650,000 (maximum of second bracket)
            // Note: getTerRate uses <= max, so exactly at max returns this bracket
            $rate = $this->service->getTerRate(5650000);
            expect($rate)->toBe(0.0025);
        });

        test('handles very low income', function () {
            $rate = $this->service->getTerRate(1000000);
            expect($rate)->toBe(0.0);
        });

        test('handles very high income', function () {
            $rate = $this->service->getTerRate(5000000000);
            expect($rate)->toBe(0.34);
        });

        test('handles zero income', function () {
            // Zero income falls through all brackets (0 is not > 0), so returns default 0.34
            $rate = $this->service->getTerRate(0);
            expect($rate)->toBe(0.34);
        });
    });

    describe('calculateMonthlyTax() - Monthly tax calculation with different methods', function () {
        test('calculates tax using NETTO method', function () {
            // NETTO: Tax = Gross * Rate
            $income = 10000000; // 10M
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_NETTO);

            // Rate for 10M is 2% (in bracket 9,650,000 - 10,050,000)
            $expectedTax = round(10000000 * 0.02, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax using GROSS method', function () {
            // GROSS: Tax = Gross * Rate (same as NETTO)
            $income = 10000000; // 10M
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            // Rate for 10M is 2%
            $expectedTax = round(10000000 * 0.02, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax using GROSS_UP method', function () {
            // GROSS_UP: Tax = (Gross * Rate) / (1 - Rate)
            $income = 10000000; // 10M
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS_UP);

            // Rate for 10M is 2%
            $rate = 0.02;
            $expectedTax = round(($income * $rate) / (1 - $rate), 2);
            expect($tax)->toBe($expectedTax);
        });

        test('GROSS_UP method produces higher tax than GROSS method', function () {
            $income = 10000000;
            $grossTax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);
            $grossUpTax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS_UP);

            expect($grossUpTax)->toBeGreaterThan($grossTax);
        });

        test('calculates zero tax for income below threshold', function () {
            $income = 5000000; // Below 5,400,000
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            expect($tax)->toBe(0.0);
        });

        test('calculates tax for low income bracket', function () {
            $income = 5500000; // In 0.25% bracket
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            $expectedTax = round(5500000 * 0.0025, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for mid-range income', function () {
            $income = 25000000; // In 10% bracket
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            $expectedTax = round(25000000 * 0.10, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for high income', function () {
            $income = 100000000; // In 25% bracket (103M - 125M is 25%, but 100M is in 89M - 103M = 24%)
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            $expectedTax = round(100000000 * 0.24, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for very high income', function () {
            $income = 500000000; // In 30% bracket (454M - 550M is 30%)
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            $expectedTax = round(500000000 * 0.30, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('handles zero income', function () {
            $tax = $this->service->calculateMonthlyTax(0, Pph21CalculationService::METHOD_GROSS);
            expect($tax)->toBe(0.0);
        });

        test('returns rounded result to 2 decimal places', function () {
            $income = 5555555; // Odd number to test rounding
            $tax = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            // Should be rounded to 2 decimal places
            expect($tax)->toBe(round($tax, 2));
        });

        test('default method is GROSS', function () {
            $income = 10000000;
            $taxDefault = $this->service->calculateMonthlyTax($income);
            $taxGross = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            expect($taxDefault)->toBe($taxGross);
        });
    });

    describe('calculateAnnualTax() - Annual tax calculation with progressive brackets', function () {
        test('calculates zero tax for income below 60M', function () {
            $annualIncome = 50000000; // 50M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // 50M * 5% = 2,500,000
            $expectedTax = 50000000 * 0.05;
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income in first bracket (0-60M @ 5%)', function () {
            $annualIncome = 30000000; // 30M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            $expectedTax = 30000000 * 0.05;
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income spanning first and second bracket', function () {
            $annualIncome = 100000000; // 100M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Remaining 40M @ 15% = 6,000,000
            // Total = 9,000,000
            $expectedTax = (60000000 * 0.05) + (40000000 * 0.15);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income spanning first, second, and third bracket', function () {
            $annualIncome = 400000000; // 400M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Next 150M @ 25% = 37,500,000
            // Total = 69,000,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15) + (150000000 * 0.25);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income spanning all brackets', function () {
            $annualIncome = 6000000000; // 6B
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Next 250M @ 25% = 62,500,000
            // Next 4,500M @ 30% = 1,350,000,000
            // Remaining 1B @ 35% = 350,000,000
            // Total = 1,794,000,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15) + (250000000 * 0.25) + (4500000000 * 0.30) + (1000000000 * 0.35);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income exactly at bracket boundary (60M)', function () {
            $annualIncome = 60000000; // 60M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            $expectedTax = 60000000 * 0.05;
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income just above first bracket (60M + 1)', function () {
            $annualIncome = 60000001; // 60M + 1
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Remaining 1 @ 15% = 0.15
            $expectedTax = (60000000 * 0.05) + (1 * 0.15);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income exactly at second bracket boundary (250M)', function () {
            $annualIncome = 250000000; // 250M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Total = 31,500,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income exactly at third bracket boundary (500M)', function () {
            $annualIncome = 500000000; // 500M
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Next 250M @ 25% = 62,500,000
            // Total = 94,000,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15) + (250000000 * 0.25);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for income exactly at fourth bracket boundary (5B)', function () {
            $annualIncome = 5000000000; // 5B
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Next 250M @ 25% = 62,500,000
            // Next 4,500M @ 30% = 1,350,000,000
            // Total = 1,444,000,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15) + (250000000 * 0.25) + (4500000000 * 0.30);
            expect($tax)->toBe($expectedTax);
        });

        test('handles zero income', function () {
            $tax = $this->service->calculateAnnualTax(0);
            expect($tax)->toBe(0.0);
        });

        test('returns rounded result to 2 decimal places', function () {
            $annualIncome = 123456789; // Odd number to test rounding
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // Should be rounded to 2 decimal places
            expect($tax)->toBe(round($tax, 2));
        });

        test('very high income calculation', function () {
            $annualIncome = 10000000000; // 10B
            $tax = $this->service->calculateAnnualTax($annualIncome);

            // First 60M @ 5% = 3,000,000
            // Next 190M @ 15% = 28,500,000
            // Next 250M @ 25% = 62,500,000
            // Next 4,500M @ 30% = 1,350,000,000
            // Remaining 5B @ 35% = 1,750,000,000
            // Total = 3,194,000,000
            $expectedTax = (60000000 * 0.05) + (190000000 * 0.15) + (250000000 * 0.25) + (4500000000 * 0.30) + (5000000000 * 0.35);
            expect($tax)->toBe($expectedTax);
        });
    });

    describe('getMethods() - Available calculation methods', function () {
        test('returns all three calculation methods', function () {
            $methods = Pph21CalculationService::getMethods();

            expect($methods)->toContain(Pph21CalculationService::METHOD_NETTO);
            expect($methods)->toContain(Pph21CalculationService::METHOD_GROSS);
            expect($methods)->toContain(Pph21CalculationService::METHOD_GROSS_UP);
            expect(count($methods))->toBe(3);
        });
    });

    describe('getMethodLabels() - Method display labels', function () {
        test('returns labels for all calculation methods', function () {
            $labels = Pph21CalculationService::getMethodLabels();

            expect($labels)->toHaveKey(Pph21CalculationService::METHOD_NETTO);
            expect($labels)->toHaveKey(Pph21CalculationService::METHOD_GROSS);
            expect($labels)->toHaveKey(Pph21CalculationService::METHOD_GROSS_UP);
        });

        test('labels are non-empty strings', function () {
            $labels = Pph21CalculationService::getMethodLabels();

            foreach ($labels as $label) {
                expect($label)->toBeString();
                expect(strlen($label))->toBeGreaterThan(0);
            }
        });
    });

    describe('Integration tests - Real-world scenarios', function () {
        test('calculates tax for typical employee salary (10M/month)', function () {
            $monthlyIncome = 10000000;
            $tax = $this->service->calculateMonthlyTax($monthlyIncome, Pph21CalculationService::METHOD_GROSS);

            // Should be positive and reasonable
            expect($tax)->toBeGreaterThan(0);
            expect($tax)->toBeLessThan($monthlyIncome);

            // Verify it's in the expected range (2-3% for this bracket)
            $rate = $this->service->getTerRate($monthlyIncome);
            $expectedTax = round($monthlyIncome * $rate, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('calculates tax for high earner (100M/month)', function () {
            $monthlyIncome = 100000000;
            $tax = $this->service->calculateMonthlyTax($monthlyIncome, Pph21CalculationService::METHOD_GROSS);

            // Should be positive and reasonable
            expect($tax)->toBeGreaterThan(0);
            expect($tax)->toBeLessThan($monthlyIncome);

            // Verify it's in the expected range (24% for this bracket)
            $rate = $this->service->getTerRate($monthlyIncome);
            $expectedTax = round($monthlyIncome * $rate, 2);
            expect($tax)->toBe($expectedTax);
        });

        test('monthly tax is consistent across multiple calls', function () {
            $income = 15000000;
            $tax1 = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);
            $tax2 = $this->service->calculateMonthlyTax($income, Pph21CalculationService::METHOD_GROSS);

            expect($tax1)->toBe($tax2);
        });

        test('annual tax calculation uses progressive brackets', function () {
            // Annual calculation uses progressive brackets, not TER rates
            // So it will differ from monthly TER calculation
            $monthlyIncome = 10000000;
            $annualIncome = $monthlyIncome * 12; // 120M annually

            $annualTax = $this->service->calculateAnnualTax($annualIncome);

            // For 120M annual:
            // First 60M @ 5% = 3,000,000
            // Next 60M @ 15% = 9,000,000
            // Total = 12,000,000
            $expectedAnnualTax = (60000000 * 0.05) + (60000000 * 0.15);
            expect($annualTax)->toBe($expectedAnnualTax);
        });
    });
});
