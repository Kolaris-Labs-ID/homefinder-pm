<?php

namespace App\Services\Hris;

class BpjsCalculationService
{
    // Legacy constants kept for backward compatibility
    const KESEHATAN_CEILING = 12000000;
    const JP_CEILING = 10547400;
    const KESEHATAN_EMPLOYEE_RATE = 0.01;
    const KESEHATAN_COMPANY_RATE = 0.04;
    const JHT_EMPLOYEE_RATE = 0.02;
    const JHT_COMPANY_RATE = 0.037;
    const JP_EMPLOYEE_RATE = 0.01;
    const JP_COMPANY_RATE = 0.02;
    const JKM_RATE = 0.003;
    const JKK_RATES = [
        'very_low' => 0.0024,
        'low' => 0.0054,
        'medium' => 0.0089,
        'high' => 0.0127,
        'very_high' => 0.0174,
    ];

    protected PayrollSettingService $settingService;

    public function __construct(?PayrollSettingService $settingService = null)
    {
        $this->settingService = $settingService ?? app(PayrollSettingService::class);
    }

    /**
     * Calculate BPJS Kesehatan contributions.
     *
     * @param float $salary Base salary for calculation
     * @return array ['employee' => float, 'company' => float]
     */
    public function calculateKesehatan(float $salary): array
    {
        $ceiling = $this->settingService->getBpjsKesehatanCeiling();
        $employeeRate = $this->settingService->getBpjsKesehatanEmployeeRate();
        $companyRate = $this->settingService->getBpjsKesehatanCompanyRate();

        $cappedSalary = min($salary, $ceiling);

        return [
            'employee' => round($cappedSalary * $employeeRate, 2),
            'company' => round($cappedSalary * $companyRate, 2),
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
        $employeeRate = $this->settingService->getBpjsJhtEmployeeRate();
        $companyRate = $this->settingService->getBpjsJhtCompanyRate();

        return [
            'employee' => round($salary * $employeeRate, 2),
            'company' => round($salary * $companyRate, 2),
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
        $ceiling = $this->settingService->getBpjsJpCeiling();
        $employeeRate = $this->settingService->getBpjsJpEmployeeRate();
        $companyRate = $this->settingService->getBpjsJpCompanyRate();

        $cappedSalary = min($salary, $ceiling);

        return [
            'employee' => round($cappedSalary * $employeeRate, 2),
            'company' => round($cappedSalary * $companyRate, 2),
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
        $rate = $this->settingService->getJkkRate($riskLevel);

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
        $rate = $this->settingService->getBpjsJkmRate();

        return round($salary * $rate, 2);
    }

    /**
     * Get JKK rate for a given risk level.
     *
     * @param string $riskLevel Risk level
     * @return float Rate as decimal
     */
    public function getJkkRate(string $riskLevel): float
    {
        return $this->settingService->getJkkRate($riskLevel);
    }

    /**
     * Calculate all BPJS components.
     *
     * @param float $salary Base salary for calculation
     * @param string $jkkRiskLevel JKK risk level
     * @param bool $companyPaid If true, employee contributions are zero (company pays all)
     * @return array Complete BPJS breakdown
     */
    public function calculateAll(float $salary, string $jkkRiskLevel = 'low', bool $companyPaid = false): array
    {
        $kesehatan = $this->calculateKesehatan($salary);
        $jht = $this->calculateJht($salary);
        $jp = $this->calculateJp($salary);
        $jkk = $this->calculateJkk($salary, $jkkRiskLevel);
        $jkm = $this->calculateJkm($salary);

        if ($companyPaid) {
            // Company pays all - employee contributions become company contributions
            $totalEmployee = 0.0;
            $totalCompany = $kesehatan['employee'] + $kesehatan['company']
                + $jht['employee'] + $jht['company']
                + $jp['employee'] + $jp['company']
                + $jkk + $jkm;

            // Update individual breakdowns (use floats for consistency)
            $kesehatan = [
                'employee' => 0.0,
                'company' => $kesehatan['employee'] + $kesehatan['company'],
            ];
            $jht = [
                'employee' => 0.0,
                'company' => $jht['employee'] + $jht['company'],
            ];
            $jp = [
                'employee' => 0.0,
                'company' => $jp['employee'] + $jp['company'],
            ];
        } else {
            $totalEmployee = $kesehatan['employee'] + $jht['employee'] + $jp['employee'];
            $totalCompany = $kesehatan['company'] + $jht['company'] + $jp['company'] + $jkk + $jkm;
        }

        return [
            'kesehatan' => $kesehatan,
            'jht' => $jht,
            'jp' => $jp,
            'jkk' => $jkk,
            'jkm' => $jkm,
            'total_employee' => round($totalEmployee, 2),
            'total_company' => round($totalCompany, 2),
            'grand_total' => round($totalEmployee + $totalCompany, 2),
            'company_paid' => $companyPaid,
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
     * Get JKK rates mapping from settings.
     *
     * @return array
     */
    public static function getJkkRatesMapping(): array
    {
        return self::JKK_RATES;
    }

    /**
     * Get JKK rates from settings (instance method).
     *
     * @return array
     */
    public function getConfiguredJkkRates(): array
    {
        return $this->settingService->getJkkRates();
    }

    /**
     * Get current BPJS rates configuration.
     *
     * @return array
     */
    public function getCurrentRates(): array
    {
        return $this->settingService->getBpjsRates();
    }
}
