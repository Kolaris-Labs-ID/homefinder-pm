<?php

use App\Models\Hris\PayrollSetting;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;

beforeEach(function () {
    $this->settingService = new PayrollSettingService();
    $this->service = new Pph21CalculationService($this->settingService);
});

describe('Pph21CalculationService uses configurable TER brackets', function () {
    it('uses configured TER brackets', function () {
        PayrollSetting::create([
            'key' => 'pph21_ter_brackets',
            'value' => [
                'brackets' => [
                    ['min' => 0, 'max' => 5000000, 'rate' => 0],
                    ['min' => 5000000, 'max' => 10000000, 'rate' => 0.05],
                    ['min' => 10000000, 'max' => PHP_INT_MAX, 'rate' => 0.10],
                ],
            ],
            'category' => PayrollSetting::CATEGORY_TAX,
            'label' => 'PPh21 Brackets',
        ]);

        $service = new Pph21CalculationService(new PayrollSettingService());
        $rate = $service->getTerRate(7000000);

        expect($rate)->toBe(0.05);
    });

    it('returns highest bracket rate for very high income', function () {
        $rate = $this->service->getTerRate(1500000000); // 1.5 billion

        expect($rate)->toBe(0.34);
    });
});

describe('Pph21CalculationService getTerRate', function () {
    it('returns 0 for income below minimum threshold', function () {
        $rate = $this->service->getTerRate(5000000);

        expect($rate)->toBe(0.0);
    });

    it('returns correct rate for 5.5M income', function () {
        $rate = $this->service->getTerRate(5500000);

        expect($rate)->toBe(0.0025); // 0.25%
    });

    it('returns correct rate for 7M income', function () {
        $rate = $this->service->getTerRate(7000000);

        expect($rate)->toBe(0.0125); // 1.25%
    });

    it('returns correct rate for 10M income', function () {
        $rate = $this->service->getTerRate(10000000);

        expect($rate)->toBe(0.02); // 2%
    });

    it('returns correct rate for 15M income', function () {
        $rate = $this->service->getTerRate(15000000);

        expect($rate)->toBe(0.06); // 6%
    });

    it('returns correct rate for 25M income', function () {
        $rate = $this->service->getTerRate(25000000);

        expect($rate)->toBe(0.10); // 10%
    });
});

describe('Pph21CalculationService calculateMonthlyTax', function () {
    it('calculates zero tax for low income', function () {
        $tax = $this->service->calculateMonthlyTax(5000000, 'gross');

        expect($tax)->toBe(0.0);
    });

    it('calculates tax using gross method', function () {
        $tax = $this->service->calculateMonthlyTax(10000000, 'gross');

        // 10M is in 2% bracket
        expect($tax)->toBe(200000.00);
    });

    it('calculates tax using netto method', function () {
        $tax = $this->service->calculateMonthlyTax(10000000, 'netto');

        // Same as gross for TER method
        expect($tax)->toBe(200000.00);
    });

    it('calculates grossed up tax', function () {
        // For gross up: Tax = (Gross * Rate) / (1 - Rate)
        // At 10M, rate is 2%
        // Tax = (10M * 0.02) / (1 - 0.02) = 200000 / 0.98 = 204081.63
        $tax = $this->service->calculateMonthlyTax(10000000, 'gross_up');

        // Use range check instead of toBeCloseTo
        expect($tax)->toBeGreaterThan(204000)
            ->and($tax)->toBeLessThan(205000);
    });

    it('calculates correct tax for higher income', function () {
        // 20M is in 9% bracket (19.75M - 24.15M)
        $tax = $this->service->calculateMonthlyTax(20000000, 'gross');

        expect($tax)->toBe(1800000.00);
    });
});

describe('Pph21CalculationService calculateAnnualTax', function () {
    it('calculates tax for income in first bracket', function () {
        // 50M annual: All in 5% bracket
        $tax = $this->service->calculateAnnualTax(50000000);

        expect($tax)->toBe(2500000.00); // 50M * 5%
    });

    it('calculates tax for income spanning two brackets', function () {
        // 100M annual: 60M at 5% + 40M at 15%
        $tax = $this->service->calculateAnnualTax(100000000);

        $expected = (60000000 * 0.05) + (40000000 * 0.15);
        expect($tax)->toBe($expected);
    });

    it('calculates tax for very high income', function () {
        // 600M annual: spans multiple brackets
        $tax = $this->service->calculateAnnualTax(600000000);

        $expected =
            (60000000 * 0.05) +    // First 60M at 5%
            (190000000 * 0.15) +   // Next 190M at 15%
            (250000000 * 0.25) +   // Next 250M at 25%
            (100000000 * 0.30);    // Last 100M at 30%

        expect($tax)->toBe($expected);
    });
});

describe('Pph21CalculationService methods helpers', function () {
    it('returns all calculation methods', function () {
        $methods = Pph21CalculationService::getMethods();

        expect($methods)->toBe(['netto', 'gross', 'gross_up']);
    });

    it('returns method labels', function () {
        $labels = Pph21CalculationService::getMethodLabels();

        expect($labels)->toHaveKeys(['netto', 'gross', 'gross_up'])
            ->and($labels['netto'])->toContain('Netto')
            ->and($labels['gross_up'])->toContain('Gross Up');
    });

    it('returns current brackets', function () {
        $brackets = $this->service->getCurrentBrackets();

        expect($brackets)->toBeArray()
            ->and(count($brackets))->toBeGreaterThan(10);
    });
});

describe('Pph21CalculationService edge cases', function () {
    it('handles zero income', function () {
        $tax = $this->service->calculateMonthlyTax(0, 'gross');

        expect($tax)->toBe(0.0);
    });

    it('handles negative income gracefully', function () {
        $rate = $this->service->getTerRate(-5000000);

        // Should return 0 or handle gracefully
        expect($rate)->toBeGreaterThanOrEqual(0);
    });

    it('handles gross up with high rate without division by zero', function () {
        // Even at 34% rate, 1 - 0.34 = 0.66, so no division by zero
        $tax = $this->service->calculateMonthlyTax(500000000, 'gross_up');

        expect($tax)->toBeGreaterThan(0);
    });
});

describe('Pph21CalculationService taxComponents (tax bearer per method)', function () {
    // base 10M, TER rate 2% => base tax 200,000; grossed-up = 200000 / 0.98 = 204,081.63
    it('gross: employee bears the tax, no allowance', function () {
        $c = $this->service->taxComponents(10000000, 'gross');

        expect($c['tunjangan_pajak'])->toBe(0.0)
            ->and($c['employee_withheld'])->toBe(200000.00)
            ->and($c['company_borne'])->toBe(0.0);
    });

    it('netto: company bears the flat tax, nothing withheld from the employee', function () {
        $c = $this->service->taxComponents(10000000, 'netto');

        expect($c['tunjangan_pajak'])->toBe(0.0)
            ->and($c['employee_withheld'])->toBe(0.0)
            ->and($c['company_borne'])->toBe(200000.00);
    });

    it('gross_up: a taxable tunjangan is added and withheld (take-home neutral)', function () {
        $c = $this->service->taxComponents(10000000, 'gross_up');

        expect($c['tunjangan_pajak'])->toEqualWithDelta(204081.63, 0.01)
            ->and($c['employee_withheld'])->toEqualWithDelta(204081.63, 0.01)
            ->and($c['company_borne'])->toEqualWithDelta(204081.63, 0.01);
    });
});
