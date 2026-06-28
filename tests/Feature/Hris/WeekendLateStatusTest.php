<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\Employee;
use App\Models\Hris\LateNotice;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\AttendanceService;
use App\Services\Hris\OvertimePayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// Week under test: Mon 2026-04-06 → Sun 2026-04-12 (matches WeekendAttendanceOvertimeTest)
const SATURDAY = '2026-04-11';
const SUNDAY = '2026-04-12';
const FRIDAY = '2026-04-10';
const MONDAY = '2026-04-06';

beforeEach(function () {
    // work_start 09:00, zero grace → a 09:30 check-in is "late" on a weekday
    AttendanceSetting::updateOrCreate([], [
        'work_start_time' => '09:00',
        'late_grace_period_minutes' => 0,
        'wfh_quota_per_week' => 2,
        'full_day_hours' => 8,
        'half_day_hours' => 4,
    ]);

    $this->service = app(AttendanceService::class);

    $this->makeLog = function (string $date, string $time, int $employeeId): AttendanceLog {
        return AttendanceLog::factory()->create([
            'employee_id' => $employeeId,
            'date' => $date,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => $date.' '.$time,
        ]);
    };

    $user = User::factory()->create();
    $this->employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
});

// ---------------------------------------------------------------------------
// computeStatus() — weekend check-ins are never LATE
// ---------------------------------------------------------------------------

it('classifies a late Saturday check-in as present, not late', function () {
    $log = ($this->makeLog)(SATURDAY, '09:30:00', $this->employee->id);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_PRESENT);
});

it('classifies a late Sunday check-in as present, not late', function () {
    $log = ($this->makeLog)(SUNDAY, '11:00:00', $this->employee->id);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_PRESENT);
});

it('still classifies a late weekday (Monday) check-in as late', function () {
    $log = ($this->makeLog)(MONDAY, '09:30:00', $this->employee->id);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_LATE);
});

it('locks the Friday/Saturday boundary: Friday late = late, Saturday late = present', function () {
    $friday = ($this->makeLog)(FRIDAY, '09:30:00', $this->employee->id);
    $saturday = ($this->makeLog)(SATURDAY, '09:30:00', $this->employee->id);

    expect($this->service->computeStatus($friday))->toBe(AttendanceLog::STATUS_LATE)
        ->and($this->service->computeStatus($saturday))->toBe(AttendanceLog::STATUS_PRESENT);
});

// ---------------------------------------------------------------------------
// computeStatus() — weekend check-ins are never HALF_DAY (any duration earns the day)
// ---------------------------------------------------------------------------

it('classifies a short (<4h) Saturday shift as present, not half day', function () {
    $log = AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => SATURDAY,
        'mode' => AttendanceLog::MODE_OFFICE,
        'source' => AttendanceLog::SOURCE_WEB,
        'check_in_time' => SATURDAY.' 08:00:00',
        'check_out_time' => SATURDAY.' 10:00:00', // 2h < half_day_hours (4)
    ]);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_PRESENT);
});

it('classifies a short (<4h) Sunday shift as present, not half day', function () {
    $log = AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => SUNDAY,
        'mode' => AttendanceLog::MODE_OFFICE,
        'source' => AttendanceLog::SOURCE_WEB,
        'check_in_time' => SUNDAY.' 08:00:00',
        'check_out_time' => SUNDAY.' 09:30:00', // 1.5h
    ]);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_PRESENT);
});

it('still classifies a short (<4h) weekday shift as half day', function () {
    $log = AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => MONDAY,
        'mode' => AttendanceLog::MODE_OFFICE,
        'source' => AttendanceLog::SOURCE_WEB,
        'check_in_time' => MONDAY.' 08:00:00',
        'check_out_time' => MONDAY.' 10:00:00', // 2h < half_day_hours (4)
    ]);

    expect($this->service->computeStatus($log))->toBe(AttendanceLog::STATUS_HALF_DAY);
});

// ---------------------------------------------------------------------------
// End-to-end: a late weekend check-in goes ONLY to overtime, never to late report
// ---------------------------------------------------------------------------

it('routes a late Saturday check-in to overtime only, with no late notice', function () {
    SalaryStructure::create([
        'employee_id' => $this->employee->id,
        'effective_date' => '2026-01-01',
        'basic_salary' => 5_000_000,
        'daily_allowance_rate' => 50_000,
        'prorate_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ]);

    Sanctum::actingAs($this->employee->user);

    // Freeze "now" to a late Saturday morning in Jakarta
    Carbon::setTestNow(Carbon::create(2026, 4, 11, 9, 30, 0, 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-in', [
        'source' => AttendanceLog::SOURCE_MOBILE,
        'mode' => AttendanceLog::MODE_OFFICE,
    ]);

    $response->assertCreated();

    $log = AttendanceLog::where('employee_id', $this->employee->id)->firstOrFail();

    // Goes to overtime: weekend present log qualifies at full daily rate
    expect($log->status)->toBe(AttendanceLog::STATUS_PRESENT);

    // Does NOT go to late report
    expect(LateNotice::where('employee_id', $this->employee->id)->count())->toBe(0);

    // And the overtime batch picks it up as a weekend_attendance session
    $batch = app(OvertimePayService::class)->generateBatch(
        Carbon::parse(MONDAY),
        Carbon::parse(SUNDAY),
        Carbon::parse('2026-04-14'),
    );
    $payment = $batch->payments()->where('employee_id', $this->employee->id)->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->total_amount)->toBe(50_000.0)
        ->and($payment->sessions_breakdown[0]['session_type'])->toBe('weekend_attendance');

    Carbon::setTestNow();
});
