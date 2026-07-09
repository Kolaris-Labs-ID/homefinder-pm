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
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function prorateFixEmployee(array $salaryOverrides = []): Employee
{
    $employee = Employee::factory()->create(['user_id' => User::factory()->create()->id]);

    SalaryStructure::create(array_merge([
        'employee_id' => $employee->id,
        'effective_date' => '2025-01-01',
        'basic_salary' => 4_600_000,
        'daily_allowance_rate' => 0,
        'prorate_enabled' => true,
        'weekly_allowance_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ], $salaryOverrides));

    return $employee;
}

function prorateFixAttend(Employee $employee, string $from, string $to, array $skipDates = []): void
{
    foreach (CarbonPeriod::create(Carbon::parse($from), Carbon::parse($to)) as $date) {
        if ($date->isWeekend() || in_array($date->toDateString(), $skipDates, true)) {
            continue;
        }

        // Raw insert with a bare 'Y-m-d' date: Eloquent's date cast stores
        // 'Y-m-d 00:00:00' in SQLite, which whereBetween('date', [..., '2025-10-31'])
        // string-compares OUT on the period's last day. MySQL DATE columns truncate,
        // so bare strings match production storage.
        DB::table('attendance_logs')->insert([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => 'office',
            'source' => AttendanceLog::SOURCE_WEB,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function prorateFixLeave(Employee $employee, LeaveType $type, string $start, string $end): LeaveRequest
{
    $startDate = Carbon::parse($start);
    $endDate = Carbon::parse($end);

    return LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'total_days' => $startDate->diffInDays($endDate) + 1,
        'status' => LeaveRequest::STATUS_APPROVED,
        'approved_at' => now(),
    ]);
}

function prorateFixSummary(Employee $employee): array
{
    return (new AttendancePayrollService(new PayrollSettingService))
        ->getAttendanceSummary($employee, Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'));
}

function prorateFixPayrollService(): PayrollCalculationService
{
    $settingService = new PayrollSettingService;

    return new PayrollCalculationService(
        new BpjsCalculationService($settingService),
        new Pph21CalculationService($settingService),
        new AttendancePayrollService($settingService),
        $settingService
    );
}

it('protects basic salary for approved annual leave with no attendance rows at all', function () {
    $employee = prorateFixEmployee();
    $annual = LeaveType::factory()->create(['code' => 'ANNUAL']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);
    prorateFixLeave($employee, $annual, '2025-10-06', '2025-10-08');

    $summary = prorateFixSummary($employee);

    expect($summary['paid_leave_days'])->toBe(3)
        ->and($summary['unpaid_leave_days'])->toBe(0)
        ->and($summary['actual_attendance_days'])->toBe(20);

    $service = new AttendancePayrollService(new PayrollSettingService);
    expect($service->calculateActualDaysWorked($summary))->toBe(23.0);
});

it('protects basic salary for approved sick leave with no attendance rows', function () {
    $employee = prorateFixEmployee();
    $sick = LeaveType::factory()->create(['code' => 'SICK']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);
    prorateFixLeave($employee, $sick, '2025-10-06', '2025-10-08');

    expect(prorateFixSummary($employee)['paid_leave_days'])->toBe(3);
});

it('counts unpaid leave days as prorating', function () {
    $employee = prorateFixEmployee();
    // Migration 2026_05_14_000001 seeds the UNPAID leave type; code is UNIQUE.
    $unpaid = LeaveType::where('code', 'UNPAID')->firstOrFail();

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);
    prorateFixLeave($employee, $unpaid, '2025-10-06', '2025-10-08');

    $summary = prorateFixSummary($employee);

    expect($summary['paid_leave_days'])->toBe(0)
        ->and($summary['unpaid_leave_days'])->toBe(3);

    $service = new AttendancePayrollService(new PayrollSettingService);
    expect($service->calculateActualDaysWorked($summary))->toBe(20.0);
});

it('leaves uncovered absence days (alpha) unprotected — prorate ratio drops', function () {
    $employee = prorateFixEmployee();

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);

    $batch = PayrunBatch::factory()->create([
        'period_start_date' => Carbon::parse('2025-10-01'),
        'period_end_date' => Carbon::parse('2025-10-31'),
        'status' => 'draft',
    ]);

    $record = prorateFixPayrollService()->calculateEmployee($employee, $batch);

    expect((float) $record->prorate_ratio)->toEqualWithDelta(20 / 23, 0.001);
});

it('does not double count days attended during approved leave', function () {
    $employee = prorateFixEmployee();
    $annual = LeaveType::factory()->create(['code' => 'ANNUAL']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31');
    prorateFixLeave($employee, $annual, '2025-10-06', '2025-10-08');

    $summary = prorateFixSummary($employee);

    expect($summary['paid_leave_days'])->toBe(0)
        ->and($summary['actual_attendance_days'])->toBe(23);

    $service = new AttendancePayrollService(new PayrollSettingService);
    expect($service->calculateActualDaysWorked($summary))->toBe(23.0);
});

it('does not count weekend days inside a leave range', function () {
    $employee = prorateFixEmployee();
    $annual = LeaveType::factory()->create(['code' => 'ANNUAL']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-03', '2025-10-06']);
    prorateFixLeave($employee, $annual, '2025-10-03', '2025-10-06');

    expect(prorateFixSummary($employee)['paid_leave_days'])->toBe(2);
});

it('end to end: converted sick leave protects converted days and prorates alpha days', function () {
    $employee = prorateFixEmployee();
    $annual = LeaveType::factory()->create(['code' => 'ANNUAL']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);
    prorateFixLeave($employee, $annual, '2025-10-06', '2025-10-06');

    $batch = PayrunBatch::factory()->create([
        'period_start_date' => Carbon::parse('2025-10-01'),
        'period_end_date' => Carbon::parse('2025-10-31'),
        'status' => 'draft',
    ]);

    $record = prorateFixPayrollService()->calculateEmployee($employee, $batch);

    expect((float) $record->prorate_ratio)->toEqualWithDelta(21 / 23, 0.001);
});

it('prefers the salary-protecting request when overlapping approvals cover the same days', function () {
    $employee = prorateFixEmployee();
    $unpaid = LeaveType::where('code', 'UNPAID')->firstOrFail();
    $annual = LeaveType::factory()->create(['code' => 'ANNUAL']);

    prorateFixAttend($employee, '2025-10-01', '2025-10-31', ['2025-10-06', '2025-10-07', '2025-10-08']);
    // UNPAID created FIRST so insertion order would pick it without the preference
    prorateFixLeave($employee, $unpaid, '2025-10-06', '2025-10-08');
    prorateFixLeave($employee, $annual, '2025-10-06', '2025-10-08');

    $summary = prorateFixSummary($employee);

    expect($summary['paid_leave_days'])->toBe(3)
        ->and($summary['unpaid_leave_days'])->toBe(0);
});
