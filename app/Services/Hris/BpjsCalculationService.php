<?php

namespace App\Services\Hris;

class BpjsCalculationService
{
    // BPJS Kesehatan ceiling (maximum wage for calculation)
    const KESEHATAN_CEILING = 12000000;

    // BPJS JP (Jaminan Pensiun) ceiling - updated March 2025
    const JP_CEILING = 10547400;

    // BPJS Kesehatan rates
    const KESEHATAN_EMPLOYEE_RATE = 0.01; // 1%
    const KESEHATAN_COMPANY_RATE = 0.04;  // 4%

    // BPJS JHT (Jaminan Hari Tua) rates - no ceiling
    const JHT_EMPLOYEE_RATE = 0.02;  // 2%
    const JHT_COMPANY_RATE = 0.037;  // 3.7%

    // BPJS JP (Jaminan Pensiun) rates
    const JP_EMPLOYEE_RATE = 0.01;  // 1%
    const JP_COMPANY_RATE = 0.02;   // 2%

    // BPJS JKM (Jaminan Kematian) rate - company only
    const JKM_RATE = 0.003;  // 0.30%

    // BPJS JKK (Jaminan Kecelakaan Kerja) rates by risk level - company only
    const JKK_RATES = [
        'very_low' => 0.0024,  // 0.24%
        'low' => 0.0054,       // 0.54%
        'medium' => 0.0089,    // 0.89%
        'high' => 0.0127,      // 1.27%
        'very_high' => 0.0174, // 1.74%
    ];

    /**
     * Calculate BPJS Kesehatan contributions.
     *
     * @param float $salary Base salary for calculation
     * @return array ['employee' => float, 'company' => float]
     */
    public function calculateKesehatan(float $salary): array
    {
        $cappedSalary = min($salary, self::KESEHATAN_CEILING);

        return [
            'employee' => round($cappedSalary * self::KESEHATAN_EMPLOYEE_RATE, 2),
            'company' => round($cappedSalary * self::KESEHATAN_COMPANY_RATE, 2),
        ];
    }

    /**
     * Calculate BPJS JHT (Jaminan Hari Tua) contributions.
     * No wage ceiling for JHT.
     *
     * @param float $salary Base salary for calculation
     * @return array ['employee' => float, 'company' => float]
     */
    public function calculateJht(float $salary): array
    {
        return [
            'employee' => round($salary * self::JHT_EMPLOYEE_RATE, 2),
            'company' => round($salary * self::JHT_COMPANY_RATE, 2),
        ];
    }

    /**
     * Calculate BPJS JP (Jaminan Pensiun) contributions.
     *
     * @param float $salary Base salary for calculation
     * @return array ['employee' => float, 'company' => float]
     */
    public function calculateJp(float $salary): array
    {
        $cappedSalary = min($salary, self::JP_CEILING);

        return [
            'employee' => round($cappedSalary * self::JP_EMPLOYEE_RATE, 2),
            'company' => round($cappedSalary * self::JP_COMPANY_RATE, 2),
        ];
    }

    /**
     * Calculate BPJS JKK (Jaminan Kecelakaan Kerja) contribution.
     * Company only, rate depends on work risk level.
     *
     * @param float $salary Base salary for calculation
     * @param string $riskLevel Risk level (very_low, low, medium, high, very_high)
     * @return float Company contribution
     */
    public function calculateJkk(float $salary, string $riskLevel = 'low'): float
    {
        $rate = self::JKK_RATES[$riskLevel] ?? self::JKK_RATES['low'];

        return round($salary * $rate, 2);
    }

    /**
     * Calculate BPJS JKM (Jaminan Kematian) contribution.
     * Company only, fixed rate.
     *
     * @param float $salary Base salary for calculation
     * @return float Company contribution
     */
    public function calculateJkm(float $salary): float
    {
        return round($salary * self::JKM_RATE, 2);
    }

    /**
     * Get JKK rate for a given risk level.
     *
     * @param string $riskLevel Risk level
     * @return float Rate as decimal
     */
    public function getJkkRate(string $riskLevel): float
    {
        return self::JKK_RATES[$riskLevel] ?? self::JKK_RATES['low'];
    }

    /**
     * Calculate all BPJS components.
     *
     * @param float $salary Base salary for calculation
     * @param string $jkkRiskLevel JKK risk level
     * @return array Complete BPJS breakdown
     */
    public function calculateAll(float $salary, string $jkkRiskLevel = 'low'): array
    {
        $kesehatan = $this->calculateKesehatan($salary);
        $jht = $this->calculateJht($salary);
        $jp = $this->calculateJp($salary);
        $jkk = $this->calculateJkk($salary, $jkkRiskLevel);
        $jkm = $this->calculateJkm($salary);

        $totalEmployee = $kesehatan['employee'] + $jht['employee'] + $jp['employee'];
        $totalCompany = $kesehatan['company'] + $jht['company'] + $jp['company'] + $jkk + $jkm;

        return [
            'kesehatan' => $kesehatan,
            'jht' => $jht,
            'jp' => $jp,
            'jkk' => $jkk,
            'jkm' => $jkm,
            'total_employee' => round($totalEmployee, 2),
            'total_company' => round($totalCompany, 2),
            'grand_total' => round($totalEmployee + $totalCompany, 2),
        ];
    }

    /**
     * Get all available JKK risk levels.
     *
     * @return array
     */
    public static function getJkkRiskLevels(): array
    {
        return array_keys(self::JKK_RATES);
    }

    /**
     * Get JKK rates mapping.
     *
     * @return array
     */
    public static function getJkkRatesMapping(): array
    {
        return self::JKK_RATES;
    }
}
