<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;
use App\Services\Hris\WeeklyAllowanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create one attendance log for a specific date.
// ---------------------------------------------------------------------------
function leaveTestLog(Employee $employee, $date, string $status = 'present', string $mode = 'office'): void
{
    AttendanceLog::create([
        'employee_id' => $employee->id,
        'date'        => $date, // Pass Carbon instance or string
        'status'      => $status,
        'mode'        => $mode,
        'source'      => AttendanceLog::SOURCE_WEB,
    ]);
}

// ---------------------------------------------------------------------------
// Helper: create $count working-day logs starting from $startDate.
//         Returns the Carbon date after the last-created log.
// ---------------------------------------------------------------------------
function addLeaveTestLogs(Employee $employee, Carbon $startDate, int $count, string $status): Carbon
{
    $date    = $startDate->copy();
    $created = 0;
    while ($created < $count) {
        if (!$date->isWeekend()) {
            // Using ->copy() instead of ->toDateString() to match format of PayrunBatch period_start_date (Carbon) 
            // This prevents SQLite whereBetween boundary comparison exclusions.
            leaveTestLog($employee, $date->copy(), $status);
            $created++;
        }
        $date->addDay();
    }
    return $date;
}

// ---------------------------------------------------------------------------
// Helper: create a fresh employee with salary structure.
// ---------------------------------------------------------------------------
function makePayrollTestEmployee(array $salaryOverrides = []): Employee
{
    $user     = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    SalaryStructure::create(array_merge([
        'employee_id'              => $employee->id,
        'effective_date'           => '2025-01-01',
        'basic_salary'             => 4_000_000,
        'daily_allowance_rate'     => 70_000,
        'prorate_enabled'          => false,
        'weekly_allowance_enabled' => false,
        'pph21_method'             => 'gross',
        'jkk_risk_level'           => 'low',
    ], $salaryOverrides));
    return $employee;
}

beforeEach(function () {
    $settingService        = new PayrollSettingService();
    $this->payrollService  = new PayrollCalculationService(
        new BpjsCalculationService($settingService),
        new Pph21CalculationService($settingService),
        new AttendancePayrollService($settingService),
        $settingService
    );
    $this->weeklyService = app(WeeklyAllowanceService::class);
});

// ============================================================
// PAYROLL: Basic Salary is NOT deducted for annual leave
// ============================================================

describe('Payroll basic salary is NOT deducted for annual leave', function () {

    it('full basic salary is paid even when employee takes annual leave (prorate disabled)', function () {
        $employee = makePayrollTestEmployee(['prorate_enabled' => false]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveTestLogs($employee, $next, 3, AttendanceLog::STATUS_LEAVE);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        expect((float) $record->basic_salary)->toBe(4_000_000.0)
            ->and((float) $record->prorate_ratio)->toBe(1.0);
    });

    it('leave days are not counted as actual_days_worked when prorate is enabled', function () {
        $employee = makePayrollTestEmployee(['prorate_enabled' => true]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveTestLogs($employee, $next, 3, AttendanceLog::STATUS_LEAVE);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        expect($record->actual_days_worked)->toBe(15);
    });

    it('prorated salary is identical for same present days regardless of leave days', function () {
        $employeeA = makePayrollTestEmployee(['prorate_enabled' => true]);
        $employeeB = makePayrollTestEmployee(['prorate_enabled' => true]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        addLeaveTestLogs($employeeA, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        $next = addLeaveTestLogs($employeeB, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveTestLogs($employeeB, $next, 3, AttendanceLog::STATUS_LEAVE);

        $recordA = $this->payrollService->calculateEmployee($employeeA, $batch);
        $recordB = $this->payrollService->calculateEmployee($employeeB, $batch);

        expect($recordA->actual_days_worked)->toBe(15)
            ->and($recordB->actual_days_worked)->toBe(15)
            ->and($recordA->prorated_basic_salary)->toBe($recordB->prorated_basic_salary);
    });
});

// ============================================================
// PAYROLL: Daily allowance IS deducted for annual leave days
// ============================================================

describe('Daily allowance in payroll is NOT paid for annual leave days', function () {

    it('daily_allowance_total only counts present days, not leave days', function () {
        $employee = makePayrollTestEmployee([
            'prorate_enabled'      => false,
            'daily_allowance_rate' => 70_000,
        ]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveTestLogs($employee, $next, 3, AttendanceLog::STATUS_LEAVE);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        expect($record->actual_days_worked)->toBe(15)
            ->and((float) $record->daily_allowance_total)->toBe(15 * 70_000.0);
    });

    it('basic salary stays the same but daily allowance is lower for employee with leave', function () {
        $employeeA = makePayrollTestEmployee(['daily_allowance_rate' => 50_000, 'prorate_enabled' => false]);
        $employeeB = makePayrollTestEmployee(['daily_allowance_rate' => 50_000, 'prorate_enabled' => false]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        addLeaveTestLogs($employeeA, Carbon::parse('2025-10-01'), 20, AttendanceLog::STATUS_PRESENT);
        $next = addLeaveTestLogs($employeeB, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveTestLogs($employeeB, $next, 5, AttendanceLog::STATUS_LEAVE);

        $recordA = $this->payrollService->calculateEmployee($employeeA, $batch);
        $recordB = $this->payrollService->calculateEmployee($employeeB, $batch);

        expect($recordA->basic_salary)->toBe($recordB->basic_salary);

        expect((float) $recordA->daily_allowance_total)->toBeGreaterThan((float) $recordB->daily_allowance_total)
            ->and((float) $recordB->daily_allowance_total)->toBe(15 * 50_000.0);
    });
});

// ============================================================
// WEEKLY ALLOWANCE: Leave days are NOT paid
// ============================================================

describe('Weekly allowance does NOT pay for annual leave days', function () {

    it('leave day is excluded from weekly day breakdown (4 present + 1 leave = 4 counted)', function () {
        $employee = makePayrollTestEmployee(['weekly_allowance_enabled' => true, 'daily_allowance_rate' => 70_000]);

        $weekStart = Carbon::parse('2025-10-06');
        $weekEnd   = Carbon::parse('2025-10-10');

        leaveTestLog($employee, Carbon::parse('2025-10-06'), AttendanceLog::STATUS_PRESENT);  // Mon
        leaveTestLog($employee, Carbon::parse('2025-10-07'), AttendanceLog::STATUS_PRESENT);  // Tue
        leaveTestLog($employee, Carbon::parse('2025-10-08'), AttendanceLog::STATUS_PRESENT);  // Wed
        leaveTestLog($employee, Carbon::parse('2025-10-09'), AttendanceLog::STATUS_PRESENT);  // Thu
        leaveTestLog($employee, Carbon::parse('2025-10-10'), AttendanceLog::STATUS_LEAVE);    // Fri

        $breakdown   = $this->weeklyService->getDaysBreakdown($employee, $weekStart, $weekEnd);
        $daysPresent = $this->weeklyService->countPresentDays($breakdown);

        expect($daysPresent)->toBe(4)
            ->and($breakdown['fri'])->toBeFalse();
    });

    it('weekly payment amount excludes leave days (3 present + 2 leave = 3 paid)', function () {
        $employee = makePayrollTestEmployee(['weekly_allowance_enabled' => true, 'daily_allowance_rate' => 70_000]);

        $weekStart = Carbon::parse('2025-10-06');
        $weekEnd   = Carbon::parse('2025-10-10');

        leaveTestLog($employee, Carbon::parse('2025-10-06'), AttendanceLog::STATUS_PRESENT);
        leaveTestLog($employee, Carbon::parse('2025-10-07'), AttendanceLog::STATUS_LEAVE);
        leaveTestLog($employee, Carbon::parse('2025-10-08'), AttendanceLog::STATUS_PRESENT);
        leaveTestLog($employee, Carbon::parse('2025-10-09'), AttendanceLog::STATUS_LEAVE);
        leaveTestLog($employee, Carbon::parse('2025-10-10'), AttendanceLog::STATUS_PRESENT);

        $payment = $this->weeklyService->calculateEmployeePayment($employee, $weekStart, $weekEnd);

        expect($payment['days_present'])->toBe(3)
            ->and($payment['total_amount'])->toBe(3 * 70_000.0);
    });

    it('weekly payment is zero when employee is on leave all week', function () {
        $employee = makePayrollTestEmployee(['weekly_allowance_enabled' => true, 'daily_allowance_rate' => 70_000]);

        $weekStart = Carbon::parse('2025-10-06');
        $weekEnd   = Carbon::parse('2025-10-10');

        foreach (['2025-10-06', '2025-10-07', '2025-10-08', '2025-10-09', '2025-10-10'] as $dateStr) {
            leaveTestLog($employee, Carbon::parse($dateStr), AttendanceLog::STATUS_LEAVE);
        }

        $payment = $this->weeklyService->calculateEmployeePayment($employee, $weekStart, $weekEnd);

        expect($payment['days_present'])->toBe(0)
            ->and($payment['total_amount'])->toBe(0.0);
    });
});
