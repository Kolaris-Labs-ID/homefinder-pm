<?php

namespace App\Services\Hris;

class Pph21CalculationService
{
    // PPh 21 calculation methods
    const METHOD_NETTO = 'netto';
    const METHOD_GROSS = 'gross';
    const METHOD_GROSS_UP = 'gross_up';

    protected PayrollSettingService $settingService;

    public function __construct(?PayrollSettingService $settingService = null)
    {
        $this->settingService = $settingService ?? app(PayrollSettingService::class);
    }

    /**
     * Get TER rate for a given monthly gross income.
     *
     * @param float $monthlyGrossIncome Monthly gross income
     * @param string $category TER category (default: A)
     * @return float TER rate as decimal
     */
    public function getTerRate(float $monthlyGrossIncome, string $category = 'A'): float
    {
        $brackets = $this->settingService->getPph21Brackets();

        foreach ($brackets as $bracket) {
            $min = $bracket['min'] ?? 0;
            $max = $bracket['max'] ?? PHP_INT_MAX;
            $rate = $bracket['rate'] ?? 0;

            if ($monthlyGrossIncome > $min && $monthlyGrossIncome <= $max) {
                return (float) $rate;
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
                // Netto: same monthly figure as gross; the COMPANY bears it
                // (bearer/withholding is resolved in taxComponents()).
                return round($monthlyGrossIncome * $rate, 2);

            case self::METHOD_GROSS:
                // Gross: tax figure withheld from the employee.
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
     * Break a month's PPh 21 into who-bears-what, per calculation method.
     *
     * - gross:    employee bears the tax (withheld from take-home).
     * - netto:    company bears the flat tax; nothing withheld from the employee.
     * - gross_up: company funds a taxable tax allowance (tunjangan pajak) added to
     *             gross income; withholding the equal tax leaves take-home unchanged.
     *
     * @param float $taxableBase Monthly taxable income (gross - employee BPJS)
     * @param string $method netto | gross | gross_up
     * @return array{tunjangan_pajak: float, employee_withheld: float, company_borne: float}
     */
    public function taxComponents(float $taxableBase, string $method): array
    {
        $baseTax = $this->calculateMonthlyTax($taxableBase, self::METHOD_GROSS);

        if ($method === self::METHOD_NETTO) {
            return ['tunjangan_pajak' => 0.0, 'employee_withheld' => 0.0, 'company_borne' => $baseTax];
        }

        if ($method === self::METHOD_GROSS_UP) {
            $tunjangan = $this->calculateMonthlyTax($taxableBase, self::METHOD_GROSS_UP);

            return ['tunjangan_pajak' => $tunjangan, 'employee_withheld' => $tunjangan, 'company_borne' => $tunjangan];
        }

        return ['tunjangan_pajak' => 0.0, 'employee_withheld' => $baseTax, 'company_borne' => 0.0];
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
            self::METHOD_NETTO => 'Netto (Pajak ditanggung perusahaan)',
            self::METHOD_GROSS => 'Gross (Pajak ditanggung karyawan)',
            self::METHOD_GROSS_UP => 'Gross Up (Pajak ditanggung perusahaan, tunjangan pajak)',
        ];
    }

    /**
     * Get current TER brackets.
     *
     * @return array
     */
    public function getCurrentBrackets(): array
    {
        return $this->settingService->getPph21Brackets();
    }
}
