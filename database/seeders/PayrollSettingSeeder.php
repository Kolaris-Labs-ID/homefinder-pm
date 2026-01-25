<?php

namespace Database\Seeders;

use App\Models\Hris\PayrollSetting;
use Illuminate\Database\Seeder;

class PayrollSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // BPJS Kesehatan Settings
            [
                'key' => 'bpjs_kes_employee_rate',
                'value' => ['rate' => 0.01],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS Kesehatan Employee Rate',
                'description' => 'Employee contribution rate for BPJS Kesehatan (1%)',
            ],
            [
                'key' => 'bpjs_kes_company_rate',
                'value' => ['rate' => 0.04],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS Kesehatan Company Rate',
                'description' => 'Company contribution rate for BPJS Kesehatan (4%)',
            ],
            [
                'key' => 'bpjs_kes_ceiling',
                'value' => ['amount' => 12000000],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS Kesehatan Wage Ceiling',
                'description' => 'Maximum wage for BPJS Kesehatan calculation (IDR 12,000,000)',
            ],

            // BPJS JHT Settings
            [
                'key' => 'bpjs_jht_employee_rate',
                'value' => ['rate' => 0.02],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JHT Employee Rate',
                'description' => 'Employee contribution rate for BPJS JHT (2%)',
            ],
            [
                'key' => 'bpjs_jht_company_rate',
                'value' => ['rate' => 0.037],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JHT Company Rate',
                'description' => 'Company contribution rate for BPJS JHT (3.7%)',
            ],

            // BPJS JP Settings
            [
                'key' => 'bpjs_jp_employee_rate',
                'value' => ['rate' => 0.01],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JP Employee Rate',
                'description' => 'Employee contribution rate for BPJS Jaminan Pensiun (1%)',
            ],
            [
                'key' => 'bpjs_jp_company_rate',
                'value' => ['rate' => 0.02],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JP Company Rate',
                'description' => 'Company contribution rate for BPJS Jaminan Pensiun (2%)',
            ],
            [
                'key' => 'bpjs_jp_ceiling',
                'value' => ['amount' => 10547400],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JP Wage Ceiling',
                'description' => 'Maximum wage for BPJS JP calculation (IDR 10,547,400 - Updated March 2025)',
            ],

            // BPJS JKM Settings
            [
                'key' => 'bpjs_jkm_rate',
                'value' => ['rate' => 0.003],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JKM Rate',
                'description' => 'Company contribution rate for BPJS Jaminan Kematian (0.30%)',
            ],

            // BPJS JKK Settings
            [
                'key' => 'bpjs_jkk_rates',
                'value' => [
                    'rates' => [
                        'very_low' => 0.0024,
                        'low' => 0.0054,
                        'medium' => 0.0089,
                        'high' => 0.0127,
                        'very_high' => 0.0174,
                    ],
                ],
                'category' => PayrollSetting::CATEGORY_BPJS,
                'label' => 'BPJS JKK Risk Level Rates',
                'description' => 'Company contribution rates for BPJS JKK by risk level (0.24% - 1.74%)',
            ],

            // PPh 21 TER Brackets (Category A: TK/0 or TK/1)
            [
                'key' => 'pph21_ter_brackets',
                'value' => [
                    'brackets' => [
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
                    ],
                ],
                'category' => PayrollSetting::CATEGORY_TAX,
                'label' => 'PPh 21 TER Brackets (Category A)',
                'description' => 'Tarif Efektif Rata-rata brackets for PPh 21 calculation (TK/0 or TK/1)',
            ],

            // General Settings
            [
                'key' => 'working_days_per_month',
                'value' => ['days' => 21],
                'category' => PayrollSetting::CATEGORY_GENERAL,
                'label' => 'Working Days Per Month',
                'description' => 'Default number of working days in a month for prorate calculations',
            ],
            [
                'key' => 'prorate_enabled',
                'value' => ['enabled' => true],
                'category' => PayrollSetting::CATEGORY_GENERAL,
                'label' => 'Prorate Enabled (Global)',
                'description' => 'Enable or disable prorate calculation globally',
            ],
        ];

        foreach ($settings as $setting) {
            PayrollSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Payroll settings seeded successfully!');
        $this->command->info('Total settings: ' . count($settings));
    }
}
