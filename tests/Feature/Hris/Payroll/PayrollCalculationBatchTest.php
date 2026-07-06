<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;
use Carbon\Carbon;

beforeEach(function () {
    $this->settingService = new PayrollSettingService;
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

function createEmployeeWithSalary(array $salaryOverrides = []): Employee
{
    $employee = Employee::factory()->create();

    SalaryStructure::create(array_merge([
        'employee_id' => $employee->id,
        'effective_date' => Carbon::parse('2025-01-01'),
        'basic_salary' => 5000000,
        'prorate_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ], $salaryOverrides));

    return $employee;
}

function createAttendance(Employee $employee, int $days, string $startDate = '2025-10-01'): void
{
    $daysCreated = 0;
    $date = Carbon::parse($startDate);
    while ($daysCreated < $days) {
        if (! $date->isWeekend()) {
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

describe('PayrollCalculationService calculatePayrun', function () {
    it('calculates payrun for all active employees', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $emp1 = createEmployeeWithSalary(['basic_salary' => 5000000]);
        $emp2 = createEmployeeWithSalary(['basic_salary' => 6000000]);

        createAttendance($emp1, 23);
        createAttendance($emp2, 23);

        $results = $this->service->calculatePayrun($batch);

        expect($results['processed'])->toBe(2)
            ->and($results['skipped'])->toBe(0)
            ->and($results['errors'])->toBeEmpty();

        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    });

    it('skips employees without salary structure', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $empWithSalary = createEmployeeWithSalary();
        $empWithoutSalary = Employee::factory()->create();

        createAttendance($empWithSalary, 23);

        $results = $this->service->calculatePayrun($batch);

        expect($results['processed'])->toBe(1)
            ->and($results['skipped'])->toBe(1);
    });

    it('throws when batch is not draft', function () {
        $batch = PayrunBatch::factory()->calculated()->create();

        expect(fn () => $this->service->calculatePayrun($batch))
            ->toThrow(\Exception::class, 'Cannot calculate');
    });

    it('updates batch status to calculated', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary();
        createAttendance($employee, 23);

        $this->service->calculatePayrun($batch);

        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED)
            ->and($batch->calculated_at)->not->toBeNull();
    });
});

describe('PayrollCalculationService recalculate', function () {
    it('recalculates a calculated batch', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary(['basic_salary' => 5000000]);
        createAttendance($employee, 23);

        // First calculation
        $this->service->calculatePayrun($batch);
        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);

        // Recalculate
        $results = $this->service->recalculate($batch);

        expect($results['processed'])->toBe(1);
        $batch->refresh();
        expect($batch->status)->toBe(PayrunBatch::STATUS_CALCULATED);
    });

    it('throws when batch is locked', function () {
        $batch = PayrunBatch::factory()->pendingFinance()->create();

        expect(fn () => $this->service->recalculate($batch))
            ->toThrow(\Exception::class, 'Cannot recalculate locked');
    });

    it('is idempotent - produces same result on multiple runs', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary(['basic_salary' => 5000000]);
        createAttendance($employee, 20);

        // First calculation
        $this->service->calculatePayrun($batch);
        $record1 = PayrollRecord::forEmployee($employee->id)->forBatch($batch->id)->first();
        $netIncome1 = (float) $record1->net_income;

        // Recalculate
        $this->service->recalculate($batch);
        $record2 = PayrollRecord::forEmployee($employee->id)->forBatch($batch->id)->first();
        $netIncome2 = (float) $record2->net_income;

        expect($netIncome1)->toBe($netIncome2);
    });
});

describe('PayrollCalculationService weekly allowance reconciliation', function () {
    it('calculates weekly allowance earned from daily rate', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary([
            'basic_salary' => 5000000,
            'daily_allowance_rate' => 70000,
            'weekly_allowance_enabled' => true,
        ]);

        createAttendance($employee, 20);

        $record = $this->service->calculateEmployee($employee, $batch);

        // 20 days * 70,000 = 1,400,000
        expect((float) $record->daily_allowance_total)->toBe(1400000.00)
            ->and((float) $record->weekly_allowance_earned)->toBe(1400000.00);
    });

    it('records weekly allowance adjustment when some already paid', function () {
        $employee = createEmployeeWithSalary([
            'basic_salary' => 5000000,
            'daily_allowance_rate' => 70000,
            'weekly_allowance_enabled' => true,
        ]);

        // Create a paid weekly batch within the period
        $weeklyBatch = WeeklyAllowanceBatch::create([
            'week_start_date' => '2025-10-06',
            'week_end_date' => '2025-10-10',
            'payment_date' => '2025-10-14',
            'status' => WeeklyAllowanceBatch::STATUS_PAID,
            'paid_at' => now(),
        ]);

        WeeklyAllowancePayment::create([
            'weekly_allowance_batch_id' => $weeklyBatch->id,
            'employee_id' => $employee->id,
            'daily_rate' => 70000,
            'days_present' => 5,
            'total_amount' => 350000,
            'status' => WeeklyAllowancePayment::STATUS_PAID,
        ]);

        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        createAttendance($employee, 20);

        $record = $this->service->calculateEmployee($employee, $batch);

        expect((float) $record->weekly_allowance_paid)->toBe(350000.00)
            ->and((float) $record->weekly_allowance_adjustment)->toBe(1050000.00); // 1,400,000 - 350,000
    });
});

describe('PayrollCalculationService variable allowances', function () {
    it('calculates variable allowances based on attendance', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary([
            'basic_salary' => 5000000,
            'allowances_variable' => [
                ['name' => 'Uang Makan', 'type' => 'attendance_based', 'per_day_amount' => 50000],
                ['name' => 'Transport', 'type' => 'attendance_based', 'per_day_amount' => 30000],
            ],
        ]);

        createAttendance($employee, 20);

        $record = $this->service->calculateEmployee($employee, $batch);

        // (50000 + 30000) * 20 = 1,600,000
        expect((float) $record->total_variable_allowances)->toBe(1600000.00);
    });
});

describe('PayrollCalculationService net income invariant', function () {
    it('net income equals gross minus total deductions', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        $employee = createEmployeeWithSalary([
            'basic_salary' => 8000000,
            'allowances_fixed' => [['name' => 'Transport', 'amount' => 500000]],
            'deductions' => [['name' => 'Loan', 'amount' => 200000]],
        ]);

        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        $expectedNet = (float) $record->gross_income - (float) $record->total_deductions;

        expect((float) $record->net_income)->toBe(round($expectedNet, 2));
    });
});

describe('PayrollCalculationService getActiveSalaryStructure', function () {
    it('returns most recent effective structure', function () {
        $employee = Employee::factory()->create();

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 4000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-06-01',
            'basic_salary' => 5000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $structure = $this->service->getActiveSalaryStructure($employee, Carbon::parse('2025-10-31'));

        expect((float) $structure->basic_salary)->toBe(5000000.0);
    });

    it('returns null when no structure exists', function () {
        $employee = Employee::factory()->create();

        $structure = $this->service->getActiveSalaryStructure($employee, Carbon::parse('2025-10-31'));

        expect($structure)->toBeNull();
    });

    it('ignores future effective dates', function () {
        $employee = Employee::factory()->create();

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-01-01',
            'basic_salary' => 4000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-01-01',
            'basic_salary' => 6000000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $structure = $this->service->getActiveSalaryStructure($employee, Carbon::parse('2025-10-31'));

        expect((float) $structure->basic_salary)->toBe(4000000.0);
    });
});

describe('PayrollCalculationService daily allowance resolves rate per work date', function () {
    it('applies the old rate to days before a mid-month change and the new rate on/after', function () {
        $batch = PayrunBatch::factory()->draft()->create([
            'period_start_date' => '2025-10-01',
            'period_end_date' => '2025-10-31',
        ]);

        // Base structure: 50k/day from the start of the year.
        $employee = createEmployeeWithSalary([
            'daily_allowance_rate' => 50000,
        ]);

        // Rate raised to 100k effective Wednesday 2025-10-15, mid-period.
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-10-15',
            'basic_salary' => 5000000,
            'prorate_enabled' => false,
            'daily_allowance_rate' => 100000,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Present every October weekday (23 total).
        createAttendance($employee, 23);

        $record = $this->service->calculateEmployee($employee, $batch);

        // 10 weekdays before Oct 15 @ 50k + 13 weekdays on/after Oct 15 @ 100k
        // = 500,000 + 1,300,000 = 1,800,000 (NOT 23 x 100k = 2,300,000).
        expect((float) $record->daily_allowance_total)->toBe(1800000.00);
    });
});
