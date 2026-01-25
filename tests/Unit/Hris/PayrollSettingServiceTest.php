<?php

use App\Models\Hris\PayrollSetting;
use App\Services\Hris\PayrollSettingService;

beforeEach(function () {
    $this->service = new PayrollSettingService();
});

describe('PayrollSettingService BPJS Rates', function () {
    it('returns default BPJS Kesehatan employee rate when not configured', function () {
        $rate = $this->service->getBpjsKesehatanEmployeeRate();

        expect($rate)->toBe(0.01);
    });

    it('returns configured BPJS Kesehatan employee rate', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_employee_rate',
            'value' => ['rate' => 0.015],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kes Employee',
        ]);

        $rate = $this->service->getBpjsKesehatanEmployeeRate();

        expect($rate)->toBe(0.015);
    });

    it('returns default BPJS Kesehatan company rate when not configured', function () {
        $rate = $this->service->getBpjsKesehatanCompanyRate();

        expect($rate)->toBe(0.04);
    });

    it('returns configured BPJS Kesehatan company rate', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_company_rate',
            'value' => ['rate' => 0.05],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kes Company',
        ]);

        $rate = $this->service->getBpjsKesehatanCompanyRate();

        expect($rate)->toBe(0.05);
    });

    it('returns default BPJS Kesehatan ceiling when not configured', function () {
        $ceiling = $this->service->getBpjsKesehatanCeiling();

        expect($ceiling)->toBe(12000000.0);
    });

    it('returns configured BPJS Kesehatan ceiling', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_ceiling',
            'value' => ['amount' => 15000000],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kes Ceiling',
        ]);

        $ceiling = $this->service->getBpjsKesehatanCeiling();

        expect($ceiling)->toBe(15000000.0);
    });

    it('returns all BPJS rates in structured format', function () {
        $rates = $this->service->getBpjsRates();

        expect($rates)->toHaveKeys(['kesehatan', 'jht', 'jp', 'jkk', 'jkm'])
            ->and($rates['kesehatan'])->toHaveKeys(['employee_rate', 'company_rate', 'ceiling'])
            ->and($rates['kesehatan']['employee_rate'])->toBe(0.01)
            ->and($rates['kesehatan']['company_rate'])->toBe(0.04)
            ->and($rates['kesehatan']['ceiling'])->toBe(12000000.0);
    });
});

describe('PayrollSettingService JHT and JP Rates', function () {
    it('returns default JHT employee rate', function () {
        $rate = $this->service->getBpjsJhtEmployeeRate();

        expect($rate)->toBe(0.02);
    });

    it('returns default JHT company rate', function () {
        $rate = $this->service->getBpjsJhtCompanyRate();

        expect($rate)->toBe(0.037);
    });

    it('returns default JP employee rate', function () {
        $rate = $this->service->getBpjsJpEmployeeRate();

        expect($rate)->toBe(0.01);
    });

    it('returns default JP company rate', function () {
        $rate = $this->service->getBpjsJpCompanyRate();

        expect($rate)->toBe(0.02);
    });

    it('returns default JP ceiling', function () {
        $ceiling = $this->service->getBpjsJpCeiling();

        expect($ceiling)->toBe(10547400.0);
    });
});

describe('PayrollSettingService JKK Rates', function () {
    it('returns default JKK rates when not configured', function () {
        $rates = $this->service->getJkkRates();

        expect($rates)->toHaveKeys(['very_low', 'low', 'medium', 'high', 'very_high'])
            ->and($rates['low'])->toBe(0.0054);
    });

    it('returns configured JKK rates', function () {
        PayrollSetting::create([
            'key' => 'bpjs_jkk_rates',
            'value' => [
                'rates' => [
                    'very_low' => 0.003,
                    'low' => 0.006,
                    'medium' => 0.009,
                    'high' => 0.013,
                    'very_high' => 0.018,
                ],
            ],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'JKK Rates',
        ]);

        $rates = $this->service->getJkkRates();

        expect($rates['low'])->toBe(0.006);
    });

    it('returns specific JKK rate for risk level', function () {
        $rate = $this->service->getJkkRate('medium');

        expect($rate)->toBe(0.0089);
    });

    it('returns low risk rate as default for unknown risk level', function () {
        $rate = $this->service->getJkkRate('unknown_level');

        expect($rate)->toBe(0.0054);
    });
});

describe('PayrollSettingService PPh21 Brackets', function () {
    it('returns default TER brackets when not configured', function () {
        $brackets = $this->service->getPph21Brackets();

        expect($brackets)->toBeArray()
            ->and(count($brackets))->toBeGreaterThan(10);
        
        // Check first bracket (0 tax up to 5.4M)
        expect($brackets[0]['min'])->toBe(0)
            ->and($brackets[0]['max'])->toBe(5400000)
            ->and($brackets[0]['rate'])->toBe(0);
    });

    it('returns configured TER brackets', function () {
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

        $brackets = $this->service->getPph21Brackets();

        expect($brackets)->toHaveCount(3)
            ->and($brackets[1]['rate'])->toBe(0.05);
    });
});

describe('PayrollSettingService Working Days', function () {
    it('returns default working days per month when not configured', function () {
        $days = $this->service->getWorkingDaysPerMonth();

        expect($days)->toBe(21);
    });

    it('returns configured working days per month', function () {
        PayrollSetting::create([
            'key' => 'working_days_per_month',
            'value' => ['days' => 22],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Working Days',
        ]);

        $days = $this->service->getWorkingDaysPerMonth();

        expect($days)->toBe(22);
    });
});

describe('PayrollSettingService Prorate Setting', function () {
    it('returns true for prorate enabled by default', function () {
        $enabled = $this->service->isProrateEnabled();

        expect($enabled)->toBeTrue();
    });

    it('returns configured prorate enabled value', function () {
        PayrollSetting::create([
            'key' => 'prorate_enabled',
            'value' => ['enabled' => false],
            'category' => PayrollSetting::CATEGORY_GENERAL,
            'label' => 'Prorate',
        ]);

        $enabled = $this->service->isProrateEnabled();

        expect($enabled)->toBeFalse();
    });
});

describe('PayrollSettingService All Settings', function () {
    it('returns all settings in structured format', function () {
        $allSettings = $this->service->getAllSettings();

        expect($allSettings)->toHaveKeys(['bpjs', 'pph21', 'general'])
            ->and($allSettings['bpjs'])->toHaveKeys(['kesehatan', 'jht', 'jp', 'jkk', 'jkm'])
            ->and($allSettings['pph21'])->toHaveKey('brackets')
            ->and($allSettings['general'])->toHaveKeys(['working_days_per_month', 'prorate_enabled']);
    });
});
