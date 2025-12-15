<?php

namespace App\Services\Hris;

class Pph21CalculationService
{
    // PPh 21 calculation methods
    const METHOD_NETTO = 'netto';
    const METHOD_GROSS = 'gross';
    const METHOD_GROSS_UP = 'gross_up';

    // TER (Tarif Efektif Rata-rata) brackets for 2024/2025
    // Based on PP 58/2023 - Monthly TER rates
    // Category A: TK/0 or TK/1 (Single or Single with 1 dependent)
    const TER_CATEGORY_A = [
        ['min' => 0, 'max' => 5400000, 'rate' => 0],
        ['min' => 5400000, 'max' => 5650000, 'rate' => 0.0025],
        ['min' => 5650000, 'max' => 5950000, 'rate' => 0.005],
        ['min' => 5950000, 'max' => 6300000, 'rate' => 0.0075],
        ['min' => 6300000, 'max' => 6750000, 'rate' => 0.01],
        ['min' => 6750000, 'max' => 7500000, 'rate' => 0.0125],
        ['min' => 7500000, 'max' => 8550000, 'rate' => 0.015],
        ['min' => 8550000, 'max' => 9650000, 'rate' => 0.0175],
        ['min' => 9650000, 'max' => 10050000, 'rate' => 0.02],
        ['min' => 10050000, 'max' => 10350000, 'rate' => 0.0225],
        ['min' => 10350000, 'max' => 10700000, 'rate' => 0.025],
        ['min' => 10700000, 'max' => 11050000, 'rate' => 0.03],
        ['min' => 11050000, 'max' => 11600000, 'rate' => 0.035],
        ['min' => 11600000, 'max' => 12500000, 'rate' => 0.04],
        ['min' => 12500000, 'max' => 13750000, 'rate' => 0.05],
        ['min' => 13750000, 'max' => 15100000, 'rate' => 0.06],
        ['min' => 15100000, 'max' => 16950000, 'rate' => 0.07],
        ['min' => 16950000, 'max' => 19750000, 'rate' => 0.08],
        ['min' => 19750000, 'max' => 24150000, 'rate' => 0.09],
        ['min' => 24150000, 'max' => 26450000, 'rate' => 0.10],
        ['min' => 26450000, 'max' => 28000000, 'rate' => 0.11],
        ['min' => 28000000, 'max' => 30050000, 'rate' => 0.12],
        ['min' => 30050000, 'max' => 32400000, 'rate' => 0.13],
        ['min' => 32400000, 'max' => 35400000, 'rate' => 0.14],
        ['min' => 35400000, 'max' => 39100000, 'rate' => 0.15],
        ['min' => 39100000, 'max' => 43850000, 'rate' => 0.16],
        ['min' => 43850000, 'max' => 47800000, 'rate' => 0.17],
        ['min' => 47800000, 'max' => 51400000, 'rate' => 0.18],
        ['min' => 51400000, 'max' => 56300000, 'rate' => 0.19],
        ['min' => 56300000, 'max' => 62200000, 'rate' => 0.20],
        ['min' => 62200000, 'max' => 68600000, 'rate' => 0.21],
        ['min' => 68600000, 'max' => 77500000, 'rate' => 0.22],
        ['min' => 77500000, 'max' => 89000000, 'rate' => 0.23],
        ['min' => 89000000, 'max' => 103000000, 'rate' => 0.24],
        ['min' => 103000000, 'max' => 125000000, 'rate' => 0.25],
        ['min' => 125000000, 'max' => 157000000, 'rate' => 0.26],
        ['min' => 157000000, 'max' => 206000000, 'rate' => 0.27],
        ['min' => 206000000, 'max' => 337000000, 'rate' => 0.28],
        ['min' => 337000000, 'max' => 454000000, 'rate' => 0.29],
        ['min' => 454000000, 'max' => 550000000, 'rate' => 0.30],
        ['min' => 550000000, 'max' => 695000000, 'rate' => 0.31],
        ['min' => 695000000, 'max' => 910000000, 'rate' => 0.32],
        ['min' => 910000000, 'max' => 1400000000, 'rate' => 0.33],
        ['min' => 1400000000, 'max' => PHP_INT_MAX, 'rate' => 0.34],
    ];

    /**
     * Get TER rate for a given monthly gross income.
     *
     * @param float $monthlyGrossIncome Monthly gross income
     * @param string $category TER category (default: A)
     * @return float TER rate as decimal
     */
    public function getTerRate(float $monthlyGrossIncome, string $category = 'A'): float
    {
        $brackets = self::TER_CATEGORY_A;

        foreach ($brackets as $bracket) {
            if ($monthlyGrossIncome > $bracket['min'] && $monthlyGrossIncome <= $bracket['max']) {
                return $bracket['rate'];
            }
        }

        // If above all brackets, use highest rate
        return 0.34;
    }

    /**
     * Calculate monthly PPh 21 using TER method.
     *
     * @param float $monthlyGrossIncome Monthly gross income (after BPJS employee deductions)
     * @param string $method Calculation method (netto, gross, gross_up)
     * @return float Monthly PPh 21 amount
     */
    public function calculateMonthlyTax(float $monthlyGrossIncome, string $method = self::METHOD_GROSS): float
    {
        $rate = $this->getTerRate($monthlyGrossIncome);

        switch ($method) {
            case self::METHOD_NETTO:
                // Netto: Tax is borne by employee, calculated on gross
                return round($monthlyGrossIncome * $rate, 2);

            case self::METHOD_GROSS:
                // Gross: Tax is borne by employee, calculated on gross
                return round($monthlyGrossIncome * $rate, 2);

            case self::METHOD_GROSS_UP:
                // Gross Up: Tax is borne by company, grossed up
                // Formula: Tax = (Gross * Rate) / (1 - Rate)
                if ($rate >= 1) {
                    return 0;
                }
                $grossedUpTax = ($monthlyGrossIncome * $rate) / (1 - $rate);
                return round($grossedUpTax, 2);

            default:
                return round($monthlyGrossIncome * $rate, 2);
        }
    }

    /**
     * Calculate annual PPh 21 (for reference/reporting).
     *
     * @param float $annualTaxableIncome Annual taxable income
     * @return float Annual PPh 21 amount
     */
    public function calculateAnnualTax(float $annualTaxableIncome): float
    {
        // Progressive tax rates for annual calculation
        $tax = 0;
        $remaining = $annualTaxableIncome;

        // Bracket 1: 0 - 60M @ 5%
        if ($remaining > 0) {
            $taxable = min($remaining, 60000000);
            $tax += $taxable * 0.05;
            $remaining -= $taxable;
        }

        // Bracket 2: 60M - 250M @ 15%
        if ($remaining > 0) {
            $taxable = min($remaining, 190000000);
            $tax += $taxable * 0.15;
            $remaining -= $taxable;
        }

        // Bracket 3: 250M - 500M @ 25%
        if ($remaining > 0) {
            $taxable = min($remaining, 250000000);
            $tax += $taxable * 0.25;
            $remaining -= $taxable;
        }

        // Bracket 4: 500M - 5B @ 30%
        if ($remaining > 0) {
            $taxable = min($remaining, 4500000000);
            $tax += $taxable * 0.30;
            $remaining -= $taxable;
        }

        // Bracket 5: > 5B @ 35%
        if ($remaining > 0) {
            $tax += $remaining * 0.35;
        }

        return round($tax, 2);
    }

    /**
     * Get all available calculation methods.
     *
     * @return array
     */
    public static function getMethods(): array
    {
        return [
            self::METHOD_NETTO,
            self::METHOD_GROSS,
            self::METHOD_GROSS_UP,
        ];
    }

    /**
     * Get method labels for display.
     *
     * @return array
     */
    public static function getMethodLabels(): array
    {
        return [
            self::METHOD_NETTO => 'Netto (Pajak ditanggung karyawan)',
            self::METHOD_GROSS => 'Gross (Pajak ditanggung karyawan)',
            self::METHOD_GROSS_UP => 'Gross Up (Pajak ditanggung perusahaan)',
        ];
    }
}
