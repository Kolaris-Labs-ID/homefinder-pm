<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\MasterHoliday;
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

describe('PayrollCalculationService prorate calculation', function () {
    it('calculates prorate correctly for partial month', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);
        
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4000000,
            'prorate_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create 15 days attendance out of 23 working days in October
        for ($i = 0; $i < 15; $i++) {
            $date = Carbon::parse('2025-10-01')->addDays($i);
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
            }
        }

        $record = $this->service->calculateEmployee($employee, $batch);

        expect($record)->not->toBeNull()
            ->and($record->full_basic_salary)->toBe('4000000.00')
            ->and((float) $record->prorate_ratio)->toBeLessThan(1.0)
            ->and((float) $record->prorated_basic_salary)->toBeLessThan(4000000);
    });

    it('does not prorate when prorate_enabled is false', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4000000,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create only 10 days attendance
        for ($i = 0; $i < 10; $i++) {
            $date = Carbon::parse('2025-10-01')->addDays($i);
            if (!$date->isWeekend()) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'status' => AttendanceLog::STATUS_PRESENT,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'source' => AttendanceLog::SOURCE_WEB,
                ]);
            }
        }

        $record = $this->service->calculateEmployee($employee, $batch);

        // Basic salary should be full amount (not prorated)
        expect($record->basic_salary)->toBe('4000000.00')
            ->and((float) $record->prorate_ratio)->toBe(1.0);
    });

    it('calculates mid-month join prorate with paid weekday holidays', function () {
        $employee = Employee::factory()->create([
            'hire_date' => '2025-10-10',
            'employment_end_date' => null,
            'is_active' => true,
        ]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        MasterHoliday::create(['date' => '2025-10-13', 'name' => 'Weekday Holiday 1', 'is_active' => true]);
        MasterHoliday::create(['date' => '2025-10-20', 'name' => 'Weekday Holiday 2', 'is_active' => true]);
        MasterHoliday::create(['date' => '2025-10-19', 'name' => 'Weekend Holiday', 'is_active' => true]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4200000,
            'allowances_fixed' => [],
            'allowances_variable' => [],
            'deductions' => [],
            'prorate_enabled' => true,
            'bpjs_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        foreach (['2025-10-10', '2025-10-14', '2025-10-15', '2025-10-16', '2025-10-17'] as $date) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
                'check_in_time' => Carbon::parse("{$date} 09:00:00"),
            ]);
        }

        $record = $this->service->calculateEmployee($employee, $batch);
        $summary = $record->details['attendance_summary'];

        expect($record)->not->toBeNull()
            ->and($record->total_working_days)->toBe(21)
            ->and($record->actual_days_worked)->toBe(7)
            ->and((float) $record->prorate_ratio)->toEqualWithDelta(7 / 21, 0.0001)
            ->and((float) $record->prorated_basic_salary)->toBe(1400000.00)
            ->and($summary['actual_attendance_days'])->toBe(5)
            ->and($summary['eligible_paid_holiday_days'])->toBe(2)
            ->and($summary['total_paid_days'])->toBe(7);
    });
});

describe('PayrollCalculationService daily allowance', function () {
    it('calculates daily allowance based on attendance', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4000000,
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create 15 days of attendance
        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 15) {
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

        $record = $this->service->calculateEmployee($employee, $batch);

        // 15 days * 70,000 = 1,050,000
        expect((float) $record->daily_allowance_total)->toBe(1050000.00)
            ->and($record->actual_days_worked)->toBe(15);
    });

    it('includes daily allowance in gross income', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4000000,
            'daily_allowance_rate' => 50000,
            'prorate_enabled' => false, // Full salary
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create 20 days attendance
        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 20) {
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

        $record = $this->service->calculateEmployee($employee, $batch);

        $expectedDailyAllowance = 20 * 50000; // 1,000,000
        $expectedGross = 4000000 + $expectedDailyAllowance;

        expect((float) $record->daily_allowance_total)->toBe(1000000.00)
            ->and((float) $record->gross_income)->toBeGreaterThanOrEqual($expectedGross);
    });
});

describe('PayrollCalculationService BPJS company paid', function () {
    it('sets BPJS deductions to zero when company paid', function () {
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
            'bpjs_paid_by' => 'company',
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create full month attendance
        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 23) {
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

        $record = $this->service->calculateEmployee($employee, $batch);

        expect($record->bpjs_company_paid)->toBeTrue()
            ->and((float) $record->bpjs_kesehatan_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jht_employee)->toBe(0.0)
            ->and((float) $record->bpjs_jp_employee)->toBe(0.0);
    });

    it('company BPJS contribution includes employee share', function () {
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
            'bpjs_paid_by' => 'company',
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create attendance
        $date = Carbon::parse('2025-10-01');
        for ($i = 0; $i < 23; $i++) {
            while ($date->isWeekend()) {
                $date->addDay();
            }
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date->copy(),
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
            ]);
            $date->addDay();
        }

        $record = $this->service->calculateEmployee($employee, $batch);

        // Company should pay both employer and employee BPJS
        // Kesehatan: 5M * (1% + 4%) = 250,000
        expect((float) $record->bpjs_kesehatan_company)->toBe(250000.00);
    });
});

describe('PayrollCalculationService full calculation', function () {
    it('calculates complete payroll matching expected format', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => Carbon::parse('2025-01-01'),
            'basic_salary' => 4000000,
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => true,
            'bpjs_paid_by' => 'company',
            'allowances_fixed' => [['name' => 'Tunjangan Pulsa', 'amount' => 50000]],
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Create 15 working days attendance
        $daysCreated = 0;
        $date = Carbon::parse('2025-10-01');
        while ($daysCreated < 15) {
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

        $record = $this->service->calculateEmployee($employee, $batch);

        // Verify prorate fields are set
        expect($record->total_working_days)->toBeGreaterThan(0)
            ->and($record->actual_days_worked)->toBe(15)
            ->and((float) $record->prorate_ratio)->toBeLessThan(1.0);

        // Verify daily allowance
        expect((float) $record->daily_allowance_total)->toBe(1050000.00);

        // Verify BPJS company paid
        expect($record->bpjs_company_paid)->toBeTrue()
            ->and((float) $record->bpjs_kesehatan_employee)->toBe(0.0);

        // Verify gross income includes all components
        expect((float) $record->gross_income)->toBeGreaterThan(0);

        // Verify net income is calculated
        expect((float) $record->net_income)->toBeGreaterThan(0)
            ->and((float) $record->net_income)->toBeLessThanOrEqual((float) $record->gross_income);
    });
});

describe('PayrollCalculationService returns null for missing salary structure', function () {
    it('returns null when employee has no salary structure', function () {
        $employee = Employee::factory()->create();
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date' => Carbon::parse('2025-10-31'),
            'status' => 'draft',
        ]);

        $record = $this->service->calculateEmployee($employee, $batch);

        expect($record)->toBeNull();
    });
});
