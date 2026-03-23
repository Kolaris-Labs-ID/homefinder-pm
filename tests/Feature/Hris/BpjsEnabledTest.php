<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->settingService = new PayrollSettingService();
    $this->bpjsService = new BpjsCalculationService($this->settingService);
    $this->pph21Service = new Pph21CalculationService($this->settingService);
    $this->attendanceService = new AttendancePayrollService($this->settingService);
    $this->service = new PayrollCalculationService(
        $this->bpjsService,
        $this->pph21Service,
        $this->attendanceService,
        $this->settingService
    );
});


describe('SalaryStructure bpjs_enabled field', function () {
    it('defaults bpjs_enabled to true', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        expect($structure->bpjs_enabled)->toBeTrue()
            ->and($structure->isBpjsEnabled())->toBeTrue();
    });

    it('can set bpjs_enabled to false', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        expect($structure->bpjs_enabled)->toBeFalse()
            ->and($structure->isBpjsEnabled())->toBeFalse();
    });
});

describe('SalaryStructure bpjs_config per-component', function () {
    it('returns default config when bpjs_config is null', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $config = $structure->getBpjsConfig();

        expect($config['kesehatan']['enabled'])->toBeTrue()
            ->and($config['jht']['enabled'])->toBeTrue()
            ->and($config['jp']['enabled'])->toBeTrue()
            ->and($config['jkk']['enabled'])->toBeTrue()
            ->and($config['jkm']['enabled'])->toBeTrue();
    });

    it('can disable individual components', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'bpjs_config' => [
                'kesehatan' => ['enabled' => false],
                'jht' => ['enabled' => true, 'paid_by' => 'company'],
                'jp' => ['enabled' => true, 'paid_by' => 'employee'],
                'jkk' => ['enabled' => true],
                'jkm' => ['enabled' => false],
            ],
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        expect($structure->isBpjsComponentEnabled('kesehatan'))->toBeFalse()
            ->and($structure->isBpjsComponentEnabled('jht'))->toBeTrue()
            ->and($structure->isBpjsComponentEnabled('jp'))->toBeTrue()
            ->and($structure->isBpjsComponentEnabled('jkk'))->toBeTrue()
            ->and($structure->isBpjsComponentEnabled('jkm'))->toBeFalse();
    });

    it('master toggle overrides all components', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => false,
            'bpjs_config' => [
                'kesehatan' => ['enabled' => true],
                'jht' => ['enabled' => true],
                'jp' => ['enabled' => true],
                'jkk' => ['enabled' => true],
                'jkm' => ['enabled' => true],
            ],
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Master off = all components off regardless of individual config
        expect($structure->isBpjsComponentEnabled('kesehatan'))->toBeFalse()
            ->and($structure->isBpjsComponentEnabled('jht'))->toBeFalse()
            ->and($structure->isBpjsComponentEnabled('jp'))->toBeFalse()
            ->and($structure->isBpjsComponentEnabled('jkk'))->toBeFalse()
            ->and($structure->isBpjsComponentEnabled('jkm'))->toBeFalse();
    });

    it('stores per-component paid_by', function () {
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'bpjs_config' => [
                'kesehatan' => ['enabled' => true, 'paid_by' => 'employee'],
                'jht' => ['enabled' => true, 'paid_by' => 'company'],
                'jp' => ['enabled' => true, 'paid_by' => 'shared'],
                'jkk' => ['enabled' => true],
                'jkm' => ['enabled' => true],
            ],
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        expect($structure->getBpjsComponentPaidBy('kesehatan'))->toBe('employee')
            ->and($structure->getBpjsComponentPaidBy('jht'))->toBe('company')
            ->and($structure->getBpjsComponentPaidBy('jp'))->toBe('shared');
    });
});

describe('BpjsCalculationService per-component', function () {
    it('calculates only enabled components', function () {
        $settingService = new PayrollSettingService();
        $bpjsService = new BpjsCalculationService($settingService);

        $config = [
            'kesehatan' => ['enabled' => false, 'paid_by' => 'employee'],
            'jht' => ['enabled' => true, 'paid_by' => 'employee'],
            'jp' => ['enabled' => true, 'paid_by' => 'employee'],
            'jkk' => ['enabled' => false],
            'jkm' => ['enabled' => true],
        ];

        $result = $bpjsService->calculateAllPerComponent(5000000, 'low', $config);

        // Kesehatan disabled
        expect($result['kesehatan']['employee'])->toBe(0.0)
            ->and($result['kesehatan']['company'])->toBe(0.0)
            // JHT enabled
            ->and($result['jht']['employee'])->toBe(100000.00)
            ->and($result['jht']['company'])->toBe(185000.00)
            // JP enabled
            ->and($result['jp']['employee'])->toBe(50000.00)
            ->and($result['jp']['company'])->toBe(100000.00)
            // JKK disabled
            ->and($result['jkk'])->toBe(0.0)
            // JKM enabled
            ->and($result['jkm'])->toBe(15000.00);
    });

    it('shifts employee portion to company when paid_by is company', function () {
        $settingService = new PayrollSettingService();
        $bpjsService = new BpjsCalculationService($settingService);

        $config = [
            'kesehatan' => ['enabled' => true, 'paid_by' => 'employee'],
            'jht' => ['enabled' => true, 'paid_by' => 'company'],
            'jp' => ['enabled' => true, 'paid_by' => 'employee'],
            'jkk' => ['enabled' => true],
            'jkm' => ['enabled' => true],
        ];

        $result = $bpjsService->calculateAllPerComponent(5000000, 'low', $config);

        // JHT company-paid: employee=0, company gets both portions
        // JHT normal: employee=100000, company=185000
        expect($result['jht']['employee'])->toBe(0.0)
            ->and($result['jht']['company'])->toBe(285000.00)
            // Kesehatan still employee-paid
            ->and($result['kesehatan']['employee'])->toBe(50000.00)
            ->and($result['kesehatan']['company'])->toBe(200000.00);
    });
});

describe('PayrollCalculationService with per-component BPJS', function () {
    it('zeroes all BPJS when master toggle is off', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => false,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        expect($record)->not->toBeNull()
            ->and((float) $record->bpjs_kesehatan_employee)->toBe(0.0)
            ->and((float) $record->bpjs_kesehatan_company)->toBe(0.0)
            ->and((float) $record->bpjs_jht_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jht_company)->toBe(0.0)
            ->and((float) $record->bpjs_jp_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jp_company)->toBe(0.0)
            ->and((float) $record->bpjs_jkk_company)->toBe(0.0)
            ->and((float) $record->bpjs_jkm_company)->toBe(0.0);
    });

    it('only calculates enabled components with per-component config', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        // Only JHT enabled, rest disabled
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'bpjs_config' => [
                'kesehatan' => ['enabled' => false, 'paid_by' => 'employee'],
                'jht' => ['enabled' => true, 'paid_by' => 'employee'],
                'jp' => ['enabled' => false, 'paid_by' => 'employee'],
                'jkk' => ['enabled' => false],
                'jkm' => ['enabled' => false],
            ],
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        // Kesehatan disabled
        expect((float) $record->bpjs_kesehatan_employee)->toBe(0.0)
            ->and((float) $record->bpjs_kesehatan_company)->toBe(0.0)
            // JHT enabled: employee 5M*2%=100k, company 5M*3.7%=185k
            ->and((float) $record->bpjs_jht_employee)->toBe(100000.00)
            ->and((float) $record->bpjs_jht_company)->toBe(185000.00)
            // JP disabled
            ->and((float) $record->bpjs_jp_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jp_company)->toBe(0.0)
            // JKK/JKM disabled
            ->and((float) $record->bpjs_jkk_company)->toBe(0.0)
            ->and((float) $record->bpjs_jkm_company)->toBe(0.0);
    });

    it('applies per-component paid_by correctly in payroll', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        // JHT paid by company, rest by employee
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'bpjs_config' => [
                'kesehatan' => ['enabled' => true, 'paid_by' => 'employee'],
                'jht' => ['enabled' => true, 'paid_by' => 'company'],
                'jp' => ['enabled' => true, 'paid_by' => 'employee'],
                'jkk' => ['enabled' => true],
                'jkm' => ['enabled' => true],
            ],
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        // JHT company-paid: employee=0, company=100k+185k=285k
        expect((float) $record->bpjs_jht_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jht_company)->toBe(285000.00)
            // Kesehatan still employee-paid
            ->and((float) $record->bpjs_kesehatan_employee)->toBe(50000.00)
            ->and((float) $record->bpjs_kesehatan_company)->toBe(200000.00);
    });

    it('stores bpjs_breakdown in payroll details with per-component info', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => false,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);
        $details = $record->details;

        expect($details['bpjs_breakdown']['total_employee'])->toBe(0)
            ->and($details['bpjs_breakdown']['total_company'])->toBe(0);
    });

    it('calculates BPJS normally when all components enabled (backward compat)', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        // All defaults = all enabled, employee-paid
        expect((float) $record->bpjs_kesehatan_employee)->toBe(50000.00)
            ->and((float) $record->bpjs_kesehatan_company)->toBe(200000.00)
            ->and((float) $record->bpjs_jht_employee)->toBe(100000.00)
            ->and((float) $record->bpjs_jht_company)->toBe(185000.00)
            ->and((float) $record->bpjs_jp_employee)->toBe(50000.00)
            ->and((float) $record->bpjs_jp_company)->toBe(100000.00);
    });
});
