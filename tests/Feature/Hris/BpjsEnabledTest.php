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

function createAttendance(Employee $employee, int $days, string $startDate = '2025-10-01'): void
{
    $daysCreated = 0;
    $date = Carbon::parse($startDate);
    while ($daysCreated < $days) {
        if (!$date->isWeekend()) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date->copy(),
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
            ]);
            $daysCreated++;
        }
        $date->addDay();
    }
}

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

describe('PayrollCalculationService with BPJS disabled', function () {
    it('zeroes all BPJS when bpjs_enabled is false', function () {
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
            ->and((float) $record->bpjs_jkm_company)->toBe(0.0)
            ->and($record->bpjs_company_paid)->toBeFalse();
    });

    it('calculates BPJS normally when bpjs_enabled is true', function () {
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

        // BPJS Kesehatan employee: 5M * 1% = 50,000
        expect((float) $record->bpjs_kesehatan_employee)->toBe(50000.00)
            ->and((float) $record->bpjs_kesehatan_company)->toBe(200000.00)
            // JHT employee: 5M * 2% = 100,000
            ->and((float) $record->bpjs_jht_employee)->toBe(100000.00)
            ->and((float) $record->bpjs_jht_company)->toBe(185000.00)
            // JP employee: 5M * 1% = 50,000
            ->and((float) $record->bpjs_jp_employee)->toBe(50000.00)
            ->and((float) $record->bpjs_jp_company)->toBe(100000.00);
    });

    it('has higher net income when BPJS is disabled', function () {
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        // Employee with BPJS enabled
        $empWithBpjs = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $empWithBpjs->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => true,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);
        createAttendance($empWithBpjs, 23);

        // Employee without BPJS
        $empNoBpjs = Employee::factory()->create();
        SalaryStructure::create([
            'employee_id' => $empNoBpjs->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 5000000,
            'bpjs_enabled' => false,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);
        createAttendance($empNoBpjs, 23);

        $recordWith = $this->service->calculateEmployee($empWithBpjs, $batch);
        $recordWithout = $this->service->calculateEmployee($empNoBpjs, $batch);

        // Same gross, but no BPJS deductions means higher net
        expect((float) $recordWith->gross_income)->toBe((float) $recordWithout->gross_income)
            ->and((float) $recordWithout->total_deductions)->toBeLessThan((float) $recordWith->total_deductions)
            ->and((float) $recordWithout->net_income)->toBeGreaterThan((float) $recordWith->net_income);
    });

    it('stores bpjs_enabled status in payroll details', function () {
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
});
