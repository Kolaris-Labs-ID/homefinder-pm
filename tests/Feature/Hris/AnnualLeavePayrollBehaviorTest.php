<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
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
// Helper: get-or-create a paid (quota-consuming) annual-leave LeaveType.
// ---------------------------------------------------------------------------
function paidAnnualLeaveType(): LeaveType
{
    return LeaveType::firstOrCreate(
        ['code' => 'ANNUAL'],
        [
            'name' => 'Annual Leave',
            'description' => 'Cuti tahunan',
            'requires_approval' => true,
            'default_annual_quota' => 12,
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ]
    );
}

// ---------------------------------------------------------------------------
// Helper: get-or-create an unpaid (zero-quota) leave type.
// ---------------------------------------------------------------------------
function unpaidLeaveType(): LeaveType
{
    return LeaveType::firstOrCreate(
        ['code' => 'UNPAID'],
        [
            'name' => 'Unpaid Leave',
            'description' => 'Cuti tanpa bayar',
            'requires_approval' => true,
            'default_annual_quota' => 0,
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ]
    );
}

// ---------------------------------------------------------------------------
// Helper: get-or-create a non-quota sick leave type.
// ---------------------------------------------------------------------------
function sickLeaveType(): LeaveType
{
    return LeaveType::firstOrCreate(
        ['code' => 'SICK'],
        [
            'name' => 'Sick Leave',
            'description' => 'Cuti sakit',
            'requires_approval' => true,
            'requires_attachment' => true,
            'default_annual_quota' => 0,
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ]
    );
}

// ---------------------------------------------------------------------------
// Helper: create $count consecutive working-day STATUS_LEAVE logs AND an
// approved LeaveRequest of the given LeaveType covering them. Returns the
// Carbon date after the last-created log.
// ---------------------------------------------------------------------------
function addLeaveLogsWithRequest(Employee $employee, Carbon $startDate, int $count, LeaveType $leaveType): Carbon
{
    $date     = $startDate->copy();
    $created  = 0;
    $first    = null;
    $last     = null;

    while ($created < $count) {
        if (!$date->isWeekend()) {
            $first ??= $date->copy();
            $last  = $date->copy();
            leaveTestLog($employee, $date->copy(), AttendanceLog::STATUS_LEAVE);
            $created++;
        }
        $date->addDay();
    }

    LeaveRequest::create([
        'employee_id'  => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date'   => $first,
        'end_date'     => $last,
        'total_days'   => $count,
        'status'       => LeaveRequest::STATUS_APPROVED,
        'approved_at'  => now(),
    ]);

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
        $annual   = paidAnnualLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employee, $next, 3, $annual);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        expect((float) $record->basic_salary)->toBe(4_000_000.0)
            ->and((float) $record->prorate_ratio)->toBe(1.0);
    });

    it('paid annual leave days ARE counted as actual_days_worked when prorate is enabled', function () {
        $employee = makePayrollTestEmployee(['prorate_enabled' => true]);
        $annual   = paidAnnualLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employee, $next, 3, $annual);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        // 15 present + 3 paid leave = 18 actual days worked
        expect($record->actual_days_worked)->toBe(18);
    });

    it('employee with paid leave has higher prorated basic salary than employee with plain absences', function () {
        $employeeA = makePayrollTestEmployee(['prorate_enabled' => true]); // 15 present, rest absent
        $employeeB = makePayrollTestEmployee(['prorate_enabled' => true]); // 15 present + 3 paid leave
        $annual    = paidAnnualLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        addLeaveTestLogs($employeeA, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        $next = addLeaveTestLogs($employeeB, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employeeB, $next, 3, $annual);

        $recordA = $this->payrollService->calculateEmployee($employeeA, $batch);
        $recordB = $this->payrollService->calculateEmployee($employeeB, $batch);

        expect($recordA->actual_days_worked)->toBe(15)
            ->and($recordB->actual_days_worked)->toBe(18)
            ->and((float) $recordB->prorated_basic_salary)
                ->toBeGreaterThan((float) $recordA->prorated_basic_salary);
    });

    it('paid leave days contribute to the prorate ratio', function () {
        $employee = makePayrollTestEmployee(['prorate_enabled' => true]);
        $annual   = paidAnnualLeaveType();

        // Paid leave protects prorate, but the final ratio still reflects the
        // current payroll denominator and employee eligibility rules.
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 21, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employee, $next, 3, $annual);

        $record = $this->payrollService->calculateEmployee($employee, $batch);
        $expectedRatio = round($record->actual_days_worked / $record->total_working_days, 4);
        $expectedProratedSalary = round(4_000_000 * ($record->actual_days_worked / $record->total_working_days), 2);
        $attendanceSummary = $record->details['attendance_summary'];

        expect($attendanceSummary['paid_leave_days'])->toBeGreaterThan(0)
            ->and((float) $record->prorate_ratio)->toBe($expectedRatio)
            ->and((float) $record->prorated_basic_salary)->toBe($expectedProratedSalary);
    });

    it('unpaid leave days still prorate the basic salary', function () {
        $employee = makePayrollTestEmployee(['prorate_enabled' => true]);
        $unpaid   = unpaidLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employee, $next, 3, $unpaid);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        $expectedRatio = round(15 / $record->total_working_days, 4);
        $expectedProratedSalary = round(4_000_000 * (15 / $record->total_working_days), 2);
        $attendanceSummary = $record->details['attendance_summary'];

        // Unpaid leave does NOT count as worked and does not pay daily allowance.
        expect($record->actual_days_worked)->toBe(15)
            ->and($record->total_working_days)->toBeGreaterThan(15)
            ->and((float) $record->prorate_ratio)->toBe($expectedRatio)
            ->and((float) $record->prorated_basic_salary)->toBe($expectedProratedSalary)
            ->and((float) $record->daily_allowance_total)->toBe(15 * 70_000.0)
            ->and($attendanceSummary['paid_leave_days'])->toBe(0)
            ->and($attendanceSummary['unpaid_leave_days'])->toBe(3);
    });
});

// ============================================================
// PAYROLL: Daily allowance IS deducted for annual leave days
// ============================================================

describe('Daily allowance in payroll is NOT paid for annual leave days', function () {

    it('daily_allowance_total only counts present days, not paid leave days', function () {
        $employee = makePayrollTestEmployee([
            'prorate_enabled'      => false,
            'daily_allowance_rate' => 70_000,
        ]);
        $annual = paidAnnualLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        $next = addLeaveTestLogs($employee, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employee, $next, 3, $annual);

        $record = $this->payrollService->calculateEmployee($employee, $batch);

        // Paid leave protects basic salary AND counts toward actual_days_worked
        expect($record->actual_days_worked)->toBe(18)
            // Daily allowance still skips leave days
            ->and((float) $record->daily_allowance_total)->toBe(15 * 70_000.0)
            // Basic salary is full because no absences exist
            ->and((float) $record->basic_salary)->toBe(4_000_000.0);
    });

    it('basic salary stays the same but daily allowance is lower for employee with leave', function () {
        $employeeA = makePayrollTestEmployee(['daily_allowance_rate' => 50_000, 'prorate_enabled' => true]);
        $employeeB = makePayrollTestEmployee(['daily_allowance_rate' => 50_000, 'prorate_enabled' => true]);
        $annual    = paidAnnualLeaveType();

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-10-01'),
            'period_end_date'   => Carbon::parse('2025-10-31'),
            'status'            => 'draft',
        ]);

        addLeaveTestLogs($employeeA, Carbon::parse('2025-10-01'), 20, AttendanceLog::STATUS_PRESENT);
        $next = addLeaveTestLogs($employeeB, Carbon::parse('2025-10-01'), 15, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($employeeB, $next, 5, $annual);

        $recordA = $this->payrollService->calculateEmployee($employeeA, $batch);
        $recordB = $this->payrollService->calculateEmployee($employeeB, $batch);

        // Both worked the same combined countable days (20 vs 15+5) so basic salary equal
        expect((float) $recordA->basic_salary)->toBe((float) $recordB->basic_salary);

        // But daily allowance differs because leave days don't pay daily allowance
        expect((float) $recordA->daily_allowance_total)->toBeGreaterThan((float) $recordB->daily_allowance_total)
            ->and((float) $recordB->daily_allowance_total)->toBe(15 * 50_000.0);
    });
});

// ============================================================
// PREDICTABLE FIXTURE: Annual/Sick/Unpaid leave payroll impact
// ============================================================

describe('Predictable one-day leave payroll impact', function () {

    it('matches the leave test fixture for basic salary and daily allowance', function () {
        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2025-02-03'),
            'period_end_date'   => Carbon::parse('2025-03-01'),
            'status'            => 'draft',
        ]);

        $salary = [
            'basic_salary' => 10_000_000,
            'daily_allowance_rate' => 50_000,
            'prorate_enabled' => true,
        ];

        $noLeaveEmployee = makePayrollTestEmployee($salary);
        $annualEmployee = makePayrollTestEmployee($salary);
        $sickEmployee = makePayrollTestEmployee($salary);
        $unpaidEmployee = makePayrollTestEmployee($salary);

        addLeaveTestLogs($noLeaveEmployee, Carbon::parse('2025-02-03'), 20, AttendanceLog::STATUS_PRESENT);

        $next = addLeaveTestLogs($annualEmployee, Carbon::parse('2025-02-03'), 19, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($annualEmployee, $next, 1, paidAnnualLeaveType());

        $next = addLeaveTestLogs($sickEmployee, Carbon::parse('2025-02-03'), 19, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($sickEmployee, $next, 1, sickLeaveType());

        $next = addLeaveTestLogs($unpaidEmployee, Carbon::parse('2025-02-03'), 19, AttendanceLog::STATUS_PRESENT);
        addLeaveLogsWithRequest($unpaidEmployee, $next, 1, unpaidLeaveType());

        $noLeave = $this->payrollService->calculateEmployee($noLeaveEmployee, $batch);
        $annual = $this->payrollService->calculateEmployee($annualEmployee, $batch);
        $sick = $this->payrollService->calculateEmployee($sickEmployee, $batch);
        $unpaid = $this->payrollService->calculateEmployee($unpaidEmployee, $batch);

        expect($noLeave->total_working_days)->toBe(20)
            ->and((float) $noLeave->basic_salary)->toBe(10_000_000.0)
            ->and((float) $noLeave->daily_allowance_total)->toBe(1_000_000.0);

        expect($annual->total_working_days)->toBe(20)
            ->and($annual->actual_days_worked)->toBe(20)
            ->and((float) $annual->basic_salary)->toBe(10_000_000.0)
            ->and((float) $annual->daily_allowance_total)->toBe(950_000.0)
            ->and($annual->details['attendance_summary']['paid_leave_days'])->toBe(1);

        expect($sick->total_working_days)->toBe(20)
            ->and($sick->actual_days_worked)->toBe(20)
            ->and((float) $sick->basic_salary)->toBe(10_000_000.0)
            ->and((float) $sick->daily_allowance_total)->toBe(950_000.0)
            ->and($sick->details['attendance_summary']['paid_leave_days'])->toBe(1);

        expect($unpaid->total_working_days)->toBe(20)
            ->and($unpaid->actual_days_worked)->toBe(19)
            ->and((float) $unpaid->prorate_ratio)->toBe(0.95)
            ->and((float) $unpaid->basic_salary)->toBe(9_500_000.0)
            ->and((float) $unpaid->daily_allowance_total)->toBe(950_000.0)
            ->and($unpaid->details['attendance_summary']['unpaid_leave_days'])->toBe(1);
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
            ->and($breakdown['fri']['present'])->toBeFalse();
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
