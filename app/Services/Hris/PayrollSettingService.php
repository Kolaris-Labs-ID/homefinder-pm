<?php

namespace App\Services\Hris;

use App\Models\Hris\PayrollSetting;

class PayrollSettingService
{
    // Default BPJS rates (used if not configured in database)
    const DEFAULT_BPJS_KESEHATAN_EMPLOYEE_RATE = 0.01;
    const DEFAULT_BPJS_KESEHATAN_COMPANY_RATE = 0.04;
    const DEFAULT_BPJS_KESEHATAN_CEILING = 12000000;

    const DEFAULT_BPJS_JHT_EMPLOYEE_RATE = 0.02;
    const DEFAULT_BPJS_JHT_COMPANY_RATE = 0.037;

    const DEFAULT_BPJS_JP_EMPLOYEE_RATE = 0.01;
    const DEFAULT_BPJS_JP_COMPANY_RATE = 0.02;
    const DEFAULT_BPJS_JP_CEILING = 10547400;

    const DEFAULT_BPJS_JKM_RATE = 0.003;

    const DEFAULT_JKK_RATES = [
        'very_low' => 0.0024,
        'low' => 0.0054,
        'medium' => 0.0089,
        'high' => 0.0127,
        'very_high' => 0.0174,
    ];

    const DEFAULT_WORKING_DAYS_PER_MONTH = 21;

    // Default PPh 21 TER brackets (Category A: TK/0 or TK/1)
    const DEFAULT_PPH21_TER_BRACKETS = [
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
     * Get all BPJS rates.
     *
     * @return array
     */
    public function getBpjsRates(): array
    {
        return [
            'kesehatan' => [
                'employee_rate' => $this->getBpjsKesehatanEmployeeRate(),
                'company_rate' => $this->getBpjsKesehatanCompanyRate(),
                'ceiling' => $this->getBpjsKesehatanCeiling(),
            ],
            'jht' => [
                'employee_rate' => $this->getBpjsJhtEmployeeRate(),
                'company_rate' => $this->getBpjsJhtCompanyRate(),
            ],
            'jp' => [
                'employee_rate' => $this->getBpjsJpEmployeeRate(),
                'company_rate' => $this->getBpjsJpCompanyRate(),
                'ceiling' => $this->getBpjsJpCeiling(),
            ],
            'jkk' => $this->getJkkRates(),
            'jkm' => [
                'rate' => $this->getBpjsJkmRate(),
            ],
        ];
    }

    /**
     * Get BPJS Kesehatan employee rate.
     */
    public function getBpjsKesehatanEmployeeRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_kes_employee_rate',
            'rate',
            self::DEFAULT_BPJS_KESEHATAN_EMPLOYEE_RATE
        );
    }

    /**
     * Get BPJS Kesehatan company rate.
     */
    public function getBpjsKesehatanCompanyRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_kes_company_rate',
            'rate',
            self::DEFAULT_BPJS_KESEHATAN_COMPANY_RATE
        );
    }

    /**
     * Get BPJS Kesehatan wage ceiling.
     */
    public function getBpjsKesehatanCeiling(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_kes_ceiling',
            'amount',
            self::DEFAULT_BPJS_KESEHATAN_CEILING
        );
    }

    /**
     * Get BPJS JHT employee rate.
     */
    public function getBpjsJhtEmployeeRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jht_employee_rate',
            'rate',
            self::DEFAULT_BPJS_JHT_EMPLOYEE_RATE
        );
    }

    /**
     * Get BPJS JHT company rate.
     */
    public function getBpjsJhtCompanyRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jht_company_rate',
            'rate',
            self::DEFAULT_BPJS_JHT_COMPANY_RATE
        );
    }

    /**
     * Get BPJS JP employee rate.
     */
    public function getBpjsJpEmployeeRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jp_employee_rate',
            'rate',
            self::DEFAULT_BPJS_JP_EMPLOYEE_RATE
        );
    }

    /**
     * Get BPJS JP company rate.
     */
    public function getBpjsJpCompanyRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jp_company_rate',
            'rate',
            self::DEFAULT_BPJS_JP_COMPANY_RATE
        );
    }

    /**
     * Get BPJS JP wage ceiling.
     */
    public function getBpjsJpCeiling(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jp_ceiling',
            'amount',
            self::DEFAULT_BPJS_JP_CEILING
        );
    }

    /**
     * Get BPJS JKM rate.
     */
    public function getBpjsJkmRate(): float
    {
        return PayrollSetting::getValueField(
            'bpjs_jkm_rate',
            'rate',
            self::DEFAULT_BPJS_JKM_RATE
        );
    }

    /**
     * Get JKK rates by risk level.
     */
    public function getJkkRates(): array
    {
        $rates = PayrollSetting::getValue('bpjs_jkk_rates');

        if ($rates && isset($rates['rates'])) {
            return $rates['rates'];
        }

        return self::DEFAULT_JKK_RATES;
    }

    /**
     * Get JKK rate for a specific risk level.
     */
    public function getJkkRate(string $riskLevel): float
    {
        $rates = $this->getJkkRates();

        return $rates[$riskLevel] ?? self::DEFAULT_JKK_RATES['low'];
    }

    /**
     * Get PPh 21 TER brackets.
     */
    public function getPph21Brackets(): array
    {
        $brackets = PayrollSetting::getValue('pph21_ter_brackets');

        if ($brackets && isset($brackets['brackets'])) {
            return $brackets['brackets'];
        }

        return self::DEFAULT_PPH21_TER_BRACKETS;
    }

    /**
     * Get working days per month.
     */
    public function getWorkingDaysPerMonth(): int
    {
        return (int) PayrollSetting::getValueField(
            'working_days_per_month',
            'days',
            self::DEFAULT_WORKING_DAYS_PER_MONTH
        );
    }

    /**
     * Check if prorate is enabled globally.
     */
    public function isProrateEnabled(): bool
    {
        return (bool) PayrollSetting::getValueField(
            'prorate_enabled',
            'enabled',
            true
        );
    }

    /**
     * Get all settings as a structured array (for export/display).
     */
    public function getAllSettings(): array
    {
        return [
            'bpjs' => $this->getBpjsRates(),
            'pph21' => [
                'brackets' => $this->getPph21Brackets(),
            ],
            'general' => [
                'working_days_per_month' => $this->getWorkingDaysPerMonth(),
                'prorate_enabled' => $this->isProrateEnabled(),
            ],
        ];
    }
}
