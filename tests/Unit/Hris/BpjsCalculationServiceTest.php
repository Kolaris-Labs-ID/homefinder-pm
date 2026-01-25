<?php

use App\Models\Hris\PayrollSetting;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollSettingService;

beforeEach(function () {
    $this->settingService = new PayrollSettingService();
    $this->service = new BpjsCalculationService($this->settingService);
});

describe('BpjsCalculationService uses configurable rates', function () {
    it('uses configured Kesehatan employee rate', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_employee_rate',
            'value' => ['rate' => 0.02], // 2% instead of default 1%
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kes Employee',
        ]);

        // Need to create new instance to pick up the setting
        $service = new BpjsCalculationService(new PayrollSettingService());
        $result = $service->calculateKesehatan(10000000);

        expect($result['employee'])->toBe(200000.00); // 2% of 10M
    });

    it('uses configured wage ceiling', function () {
        PayrollSetting::create([
            'key' => 'bpjs_kes_ceiling',
            'value' => ['amount' => 15000000], // 15M instead of default 12M
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS Kes Ceiling',
        ]);

        $service = new BpjsCalculationService(new PayrollSettingService());
        $result = $service->calculateKesehatan(20000000);

        // Should cap at 15M ceiling, not 12M
        expect($result['employee'])->toBe(150000.00); // 1% of 15M
    });

    it('uses configured JP ceiling', function () {
        PayrollSetting::create([
            'key' => 'bpjs_jp_ceiling',
            'value' => ['amount' => 12000000], // Higher than default 10.5M
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'BPJS JP Ceiling',
        ]);

        $service = new BpjsCalculationService(new PayrollSettingService());
        $result = $service->calculateJp(15000000);

        // Should cap at 12M ceiling
        expect($result['employee'])->toBe(120000.00); // 1% of 12M
    });

    it('uses configured JKK rates', function () {
        PayrollSetting::create([
            'key' => 'bpjs_jkk_rates',
            'value' => [
                'rates' => [
                    'very_low' => 0.003,
                    'low' => 0.006,
                    'medium' => 0.010,
                    'high' => 0.015,
                    'very_high' => 0.020,
                ],
            ],
            'category' => PayrollSetting::CATEGORY_BPJS,
            'label' => 'JKK Rates',
        ]);

        $service = new BpjsCalculationService(new PayrollSettingService());
        $result = $service->calculateJkk(10000000, 'low');

        expect($result)->toBe(60000.00); // 0.6% of 10M
    });
});

describe('BpjsCalculationService company paid option', function () {
    it('returns zero employee deduction when company paid', function () {
        $result = $this->service->calculateAll(10000000, 'low', companyPaid: true);

        expect($result['total_employee'])->toBe(0.0)
            ->and($result['company_paid'])->toBeTrue();
    });

    it('shifts employee BPJS to company when company paid', function () {
        // Calculate without company paid first
        $normalResult = $this->service->calculateAll(10000000, 'low', companyPaid: false);
        $normalEmployeeTotal = $normalResult['total_employee'];
        $normalCompanyTotal = $normalResult['total_company'];

        // Calculate with company paid
        $companyPaidResult = $this->service->calculateAll(10000000, 'low', companyPaid: true);

        // Company should now pay employee's share too
        expect($companyPaidResult['total_company'])->toBe($normalEmployeeTotal + $normalCompanyTotal);
    });

    it('sets individual employee contributions to zero when company paid', function () {
        $result = $this->service->calculateAll(10000000, 'low', companyPaid: true);

        expect($result['kesehatan']['employee'])->toBe(0.0)
            ->and($result['jht']['employee'])->toBe(0.0)
            ->and($result['jp']['employee'])->toBe(0.0);
    });

    it('increases company Kesehatan contribution when company paid', function () {
        // Normal calculation
        $normal = $this->service->calculateKesehatan(10000000);
        $expectedCompanyPaid = $normal['employee'] + $normal['company'];

        // Company paid - full contribution
        $result = $this->service->calculateAll(10000000, 'low', companyPaid: true);

        expect($result['kesehatan']['company'])->toBe($expectedCompanyPaid);
    });
});

describe('BpjsCalculationService calculateAll', function () {
    it('calculates all BPJS components', function () {
        $result = $this->service->calculateAll(10000000, 'low');

        expect($result)->toHaveKeys([
            'kesehatan', 'jht', 'jp', 'jkk', 'jkm',
            'total_employee', 'total_company', 'grand_total'
        ]);
    });

    it('calculates correct totals', function () {
        $result = $this->service->calculateAll(10000000, 'low');

        $expectedEmployeeTotal = 
            $result['kesehatan']['employee'] +
            $result['jht']['employee'] +
            $result['jp']['employee'];

        $expectedCompanyTotal =
            $result['kesehatan']['company'] +
            $result['jht']['company'] +
            $result['jp']['company'] +
            $result['jkk'] +
            $result['jkm'];

        expect($result['total_employee'])->toBe(round($expectedEmployeeTotal, 2))
            ->and($result['total_company'])->toBe(round($expectedCompanyTotal, 2));
    });
});

describe('BpjsCalculationService wage ceilings', function () {
    it('caps Kesehatan calculation at ceiling', function () {
        $result = $this->service->calculateKesehatan(20000000);

        // Capped at 12M (default ceiling)
        expect($result['employee'])->toBe(120000.00) // 1% of 12M
            ->and($result['company'])->toBe(480000.00); // 4% of 12M
    });

    it('caps JP calculation at ceiling', function () {
        $result = $this->service->calculateJp(20000000);

        // Capped at 10,547,400 (default ceiling)
        expect($result['employee'])->toBe(105474.00) // 1% of 10.5M
            ->and($result['company'])->toBe(210948.00); // 2% of 10.5M
    });

    it('does not cap JHT calculation', function () {
        $result = $this->service->calculateJht(20000000);

        // No ceiling for JHT
        expect($result['employee'])->toBe(400000.00) // 2% of 20M
            ->and($result['company'])->toBe(740000.00); // 3.7% of 20M
    });
});

describe('BpjsCalculationService JKK risk levels', function () {
    it('calculates JKK for very low risk', function () {
        $result = $this->service->calculateJkk(10000000, 'very_low');

        expect($result)->toBe(24000.00); // 0.24%
    });

    it('calculates JKK for low risk', function () {
        $result = $this->service->calculateJkk(10000000, 'low');

        expect($result)->toBe(54000.00); // 0.54%
    });

    it('calculates JKK for medium risk', function () {
        $result = $this->service->calculateJkk(10000000, 'medium');

        expect($result)->toBe(89000.00); // 0.89%
    });

    it('calculates JKK for high risk', function () {
        $result = $this->service->calculateJkk(10000000, 'high');

        expect($result)->toBe(127000.00); // 1.27%
    });

    it('calculates JKK for very high risk', function () {
        $result = $this->service->calculateJkk(10000000, 'very_high');

        expect($result)->toBe(174000.00); // 1.74%
    });

    it('defaults to low risk for unknown level', function () {
        $result = $this->service->calculateJkk(10000000, 'unknown');

        expect($result)->toBe(54000.00); // defaults to low (0.54%)
    });
});

describe('BpjsCalculationService getCurrentRates', function () {
    it('returns current BPJS rates configuration', function () {
        $rates = $this->service->getCurrentRates();

        expect($rates)->toHaveKeys(['kesehatan', 'jht', 'jp', 'jkk', 'jkm']);
    });
});
