<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\BpjsCalculationService;
use App\Services\Hris\OvertimePayService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayrollSettingService;
use App\Services\Hris\Pph21CalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Week under test: Mon 2026-04-06 → Sun 2026-04-12
// Saturday = 2026-04-11, Sunday = 2026-04-12
const WEEK_START = '2026-04-06';
const WEEK_END = '2026-04-12';
const PAYMENT_DT = '2026-04-14';
const DAILY_RATE = 50_000.0;

function makeEmployee(): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => '2026-01-01',
        'basic_salary' => 5_000_000,
        'daily_allowance_rate' => DAILY_RATE,
        'prorate_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ]);

    return [$user, $employee];
}

function generateBatch(): OvertimePayBatch
{
    return app(OvertimePayService::class)->generateBatch(
        Carbon::parse(WEEK_START),
        Carbon::parse(WEEK_END),
        Carbon::parse(PAYMENT_DT),
    );
}

// ---------------------------------------------------------------------------
// Weekend (Sat/Sun) attendance → OT session
// ---------------------------------------------------------------------------

describe('Weekend attendance → OvertimePayment session', function () {

    it('adds a session for Saturday present log at the full daily rate', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and($payment->total_sessions)->toBe(1)
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE);

        $session = $payment->sessions_breakdown[0];
        expect($session['session_type'])->toBe('weekend_attendance')
            ->and((float) $session['pay'])->toBe(DAILY_RATE)
            ->and($session['date'])->toBe('2026-04-11');
    });

    it('adds a session for Sunday present log at the full daily rate', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-12', // Sunday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-12 09:00:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE);
    });

    it('grants the full daily rate even when weekend log status is late (no penalty)', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_LATE,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 09:30:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE);
    });

    it('pays half_day weekend logs at the full daily rate (any duration earns the day)', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_HALF_DAY,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE)
            ->and($payment->sessions_breakdown[0]['session_type'])->toBe('weekend_attendance');
    });

    it('accumulates two weekend days into a single payment', function () {
        [, $employee] = makeEmployee();

        foreach (['2026-04-11', '2026-04-12'] as $date) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
                'check_in_time' => $date.' 08:00:00',
            ]);
        }

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and($payment->total_sessions)->toBe(2)
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE * 2);
    });

});

// ---------------------------------------------------------------------------
// Weekday holiday attendance → OT session
// ---------------------------------------------------------------------------

describe('Weekday holiday attendance → OvertimePayment session', function () {

    it('adds a holiday_attendance session for a weekday with status=holiday and check_in', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-07', // Tuesday — weekday
            'status' => AttendanceLog::STATUS_HOLIDAY,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-07 08:00:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->not->toBeNull()
            ->and((float) $payment->total_amount)->toBe(DAILY_RATE);

        $session = $payment->sessions_breakdown[0];
        expect($session['session_type'])->toBe('holiday_attendance');
    });

    it('skips holiday log without check_in (employee did not actually work)', function () {
        [, $employee] = makeEmployee();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-07',
            'status' => AttendanceLog::STATUS_HOLIDAY,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            // check_in_time intentionally omitted
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        expect($payment)->toBeNull();
    });

});

// ---------------------------------------------------------------------------
// Merge with regular OvertimeLog sessions
// ---------------------------------------------------------------------------

describe('Weekend attendance merged with regular OvertimeLog sessions', function () {

    it('merges weekend session into the same OvertimePayment as OvertimeLog sessions', function () {
        [$user, $employee] = makeEmployee();

        $manager = User::factory()->create();

        // Regular OvertimeLog on a weekday
        OvertimeLog::create([
            'user_id' => $user->id,
            'manager_id' => $manager->id,
            'task_name' => 'Weekday task',
            'start_time' => Carbon::parse('2026-04-07 18:00'),
            'end_time' => Carbon::parse('2026-04-07 20:00'),
            'duration_minutes' => 120,
            'hourly_rate' => 30_000,
            'status' => OvertimeLog::STATUS_APPROVED,
        ]);

        // Weekend attendance on Saturday
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11',
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        $batch = generateBatch();
        $payments = $batch->payments()->where('employee_id', $employee->id)->get();

        // Must be exactly one payment record (merged)
        expect($payments->count())->toBe(1);

        $payment = $payments->first();
        // 2 sessions: 1 OvertimeLog + 1 weekend attendance
        expect($payment->total_sessions)->toBe(2);
        // OT pay: first hour 1.5× + second hour 2× = (30,000×1.5) + (30,000×2.0) = 105,000
        $otPay = 30_000 * 1.5 + 30_000 * 2.0;
        $expectedTotal = round($otPay + DAILY_RATE, 2);
        expect((float) $payment->total_amount)->toBe($expectedTotal);
    });

});

// ---------------------------------------------------------------------------
// Daily allowance exclusion (no double-pay)
// ---------------------------------------------------------------------------

describe('Weekend logs excluded from payroll daily allowance', function () {

    it('does not include weekend present log in the monthly daily allowance total', function () {
        $settingService = new PayrollSettingService;
        $bpjsService = new BpjsCalculationService($settingService);
        $pph21Service = new Pph21CalculationService($settingService);
        $attendanceService = new AttendancePayrollService($settingService);
        $service = new PayrollCalculationService(
            $bpjsService,
            $pph21Service,
            $attendanceService,
            $settingService,
        );

        $employee = Employee::factory()->create();

        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-01-01',
            'basic_salary' => 5_000_000,
            'daily_allowance_rate' => DAILY_RATE,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // One weekday present log (Mon 2026-04-06)
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-06', // Monday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-06 08:00:00',
        ]);

        // One weekend present log (Sat 2026-04-11) — must NOT add to payroll daily allowance
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        $batch = \App\Models\Hris\PayrunBatch::factory()->create([
            'period_start_date' => '2026-04-01',
            'period_end_date' => '2026-04-30',
            'status' => 'draft',
        ]);

        $record = $service->calculateEmployee($employee, $batch);

        // Only the Monday log contributes → daily_allowance_total = 1 × DAILY_RATE
        expect((float) $record->daily_allowance_total)->toBe(DAILY_RATE);
    });

});

// ---------------------------------------------------------------------------
// AttendancePayrollService — weekend_work_days summary & prorate exclusion
// ---------------------------------------------------------------------------

describe('AttendancePayrollService weekend_work_days tracking', function () {

    it('counts weekend present days in weekend_work_days summary key', function () {
        $service = app(AttendancePayrollService::class);
        $employee = Employee::factory()->create();

        // 3 weekday logs
        foreach (['2026-04-06', '2026-04-07', '2026-04-08'] as $date) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $date,
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
            ]);
        }

        // 1 weekend log
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $summary = $service->getAttendanceSummary(
            $employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        expect($summary['weekend_work_days'])->toBe(1)
            ->and($summary['present_days'])->toBe(4); // 4 total including Saturday
    });

    it('excludes weekend_work_days from actual_days_worked to protect prorate accuracy', function () {
        $service = app(AttendancePayrollService::class);
        $employee = Employee::factory()->create();

        // 1 weekday + 1 weekend (same employee)
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-06',
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $summary = $service->getAttendanceSummary(
            $employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        $actualDays = $service->calculateActualDaysWorked($summary);

        // Only the 1 weekday counts; weekend excluded
        expect($actualDays)->toBe(1.0);
    });

});

// ---------------------------------------------------------------------------
// Overtime weekend sessions resolve the daily rate by the work date
// ---------------------------------------------------------------------------

describe('Overtime weekend sessions resolve daily rate per work date', function () {

    it('pays Saturday at the old rate and Sunday at the new rate when the rate changes mid-weekend', function () {
        [, $employee] = makeEmployee(); // base structure: 50k effective 2026-01-01

        // Rate raised to 100k effective Sunday 2026-04-12 (the week end).
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-04-12',
            'basic_salary' => 5_000_000,
            'daily_allowance_rate' => 100_000,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday — old rate
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-12', // Sunday — new rate
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-12 09:00:00',
        ]);

        $batch = generateBatch();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();

        // Sat @ 50k + Sun @ 100k = 150k (NOT 2 x 100k = 200k).
        expect((float) $payment->total_amount)->toBe(150_000.0);

        $byDate = collect($payment->sessions_breakdown)->keyBy('date');
        expect((float) $byDate['2026-04-11']['pay'])->toBe(50_000.0)
            ->and((float) $byDate['2026-04-12']['pay'])->toBe(100_000.0);
    });

});
