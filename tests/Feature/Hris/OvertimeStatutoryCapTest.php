<?php

use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\OvertimePayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Week under test: Mon 2026-04-06 → Sun 2026-04-12. 04-06..04-10 are weekdays.
const CAP_WEEK_START = '2026-04-06';
const CAP_WEEK_END = '2026-04-12';
const CAP_PAYMENT_DT = '2026-04-14';

// round(5_000_000 / 173, 2)
const CAP_RATE = 28901.73;

function capEmployee(): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => '2026-01-01',
        'basic_salary' => 5_000_000,
        'daily_allowance_rate' => 50_000,
        'prorate_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ]);

    return [$user, $employee];
}

function capLog(User $user, string $date, int $minutes, int $startHour = 18): OvertimeLog
{
    $start = Carbon::parse($date)->setTime($startHour, 0);

    return OvertimeLog::create([
        'user_id' => $user->id,
        'manager_id' => User::factory()->create()->id,
        'task_name' => "OT {$date} {$startHour}:00",
        'start_time' => $start,
        'end_time' => $start->copy()->addMinutes($minutes),
        'duration_minutes' => $minutes,
        'hourly_rate' => 999_999, // deliberately wrong: proves re-resolution
        'status' => OvertimeLog::STATUS_APPROVED,
    ]);
}

/**
 * Mirrors OvertimeLog::calculateEstimatedPay() term-by-term rather than folding
 * to a single multiplier — folding lands on the other side of a rounding boundary.
 */
function capPay(int $minutes, float $rate = CAP_RATE): float
{
    if ($minutes <= 0) {
        return 0.0;
    }

    $hours = $minutes / 60;

    if ($hours <= 1) {
        return $rate * 1.5 * $hours;
    }

    return $rate * 1.5 + $rate * 2.0 * ($hours - 1);
}

function capGenerate(): OvertimePayBatch
{
    return app(OvertimePayService::class)->generateBatch(
        Carbon::parse(CAP_WEEK_START),
        Carbon::parse(CAP_WEEK_END),
        Carbon::parse(CAP_PAYMENT_DT),
    );
}

// -----------------------------------------------------------------------------
// Daily ceiling — PP 35/2021 art. 26: 4 hours per day
// -----------------------------------------------------------------------------

test('calculateEstimatedPay prices at most 4 hours regardless of logged duration', function () {
    $log = new OvertimeLog(['duration_minutes' => 382, 'hourly_rate' => 36_560.69]);
    $log->hourly_rate = 36_560.69;

    // The real-world case: 6h22m logged.
    expect(round($log->calculateEstimatedPay(), 2))
        ->toBe(round(capPay(240, 36_560.69), 2));
});

test('a session at exactly 4 hours is untouched by the ceiling', function () {
    $log = new OvertimeLog(['duration_minutes' => 240]);
    $log->hourly_rate = CAP_RATE;

    expect(round($log->calculateEstimatedPay(), 2))->toBe(round(capPay(240), 2));
    expect($log->isCapped())->toBeFalse();
    expect($log->payableMinutes())->toBe(240);
});

test('the full logged duration survives on the record when pay is capped', function () {
    [$user] = capEmployee();
    $log = capLog($user, CAP_WEEK_START, 382);

    capGenerate();

    expect($log->fresh()->duration_minutes)->toBe(382);
});

test('a 6h22m session is paid as 4h in the batch', function () {
    [$user, $employee] = capEmployee();
    capLog($user, CAP_WEEK_START, 382);

    $batch = capGenerate();
    $payment = $batch->payments()->where('employee_id', $employee->id)->first();

    expect((float) $payment->total_amount)->toBe(round(capPay(240), 2));

    $session = $payment->sessions_breakdown[0];
    expect($session['duration_minutes'])->toBe(382);
    expect($session['payable_minutes'])->toBe(240);
    expect($session['excess_minutes'])->toBe(142);
});

test('two sessions on the same day share one 4-hour daily ceiling', function () {
    [$user, $employee] = capEmployee();
    capLog($user, CAP_WEEK_START, 180, startHour: 9);
    capLog($user, CAP_WEEK_START, 180, startHour: 18);

    $batch = capGenerate();
    $payment = $batch->payments()->where('employee_id', $employee->id)->first();

    // Oldest-first: 180m fully paid, the later session gets the remaining 60m.
    $expected = round(capPay(180) + capPay(60), 2);
    expect((float) $payment->total_amount)->toBe($expected);

    $minutes = collect($payment->sessions_breakdown)->pluck('payable_minutes');
    expect($minutes->sum())->toBe(240);
});

test('an overnight session is attributed to the day it started on', function () {
    [$user, $employee] = capEmployee();
    // Starts 23:00 Mon, ends 03:00 Tue — must consume Monday's ceiling, not Tuesday's.
    capLog($user, CAP_WEEK_START, 240, startHour: 23);
    capLog($user, '2026-04-07', 240, startHour: 18);

    $batch = capGenerate();
    $payment = $batch->payments()->where('employee_id', $employee->id)->first();

    // Two separate days, each within its own ceiling — nothing is trimmed.
    expect((float) $payment->total_amount)->toBe(round(capPay(240) * 2, 2));
});

// -----------------------------------------------------------------------------
// Weekly ceiling — PP 35/2021 art. 26: 18 hours per week
// -----------------------------------------------------------------------------

test('the weekly 18-hour ceiling trims the last sessions of the week', function () {
    [$user, $employee] = capEmployee();

    // 5 weekdays × 4h = 20h logged, 2h over the weekly ceiling.
    foreach (['2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10'] as $date) {
        capLog($user, $date, 240);
    }

    $batch = capGenerate();
    $payment = $batch->payments()->where('employee_id', $employee->id)->first();

    // Oldest-first: four full days, then 120m of the fifth.
    $expected = round((capPay(240) * 4) + capPay(120), 2);
    expect((float) $payment->total_amount)->toBe($expected);

    $minutes = collect($payment->sessions_breakdown)->pluck('payable_minutes');
    expect($minutes->sum())->toBe(OvertimeLog::MAX_WEEKLY_PAYABLE_MINUTES);
});

test('hours beyond the weekly ceiling are recorded as excess rather than dropped silently', function () {
    [$user, $employee] = capEmployee();

    foreach (['2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10'] as $date) {
        capLog($user, $date, 240);
    }

    $batch = capGenerate();
    $payment = $batch->payments()->where('employee_id', $employee->id)->first();

    $excess = collect($payment->sessions_breakdown)->sum('excess_minutes');
    expect($excess)->toBe(120);
});

// -----------------------------------------------------------------------------
// Recalculation must agree with generation
// -----------------------------------------------------------------------------

test('recalculateBatch applies the same ceilings as generateBatch', function () {
    [$user, $employee] = capEmployee();
    capLog($user, CAP_WEEK_START, 382);
    capLog($user, '2026-04-07', 300);

    $batch = capGenerate();
    $generated = (float) $batch->payments()->where('employee_id', $employee->id)->first()->total_amount;

    $recalculated = app(OvertimePayService::class)->recalculateBatch($batch->fresh());
    $after = (float) $recalculated->payments()->where('employee_id', $employee->id)->first()->total_amount;

    expect($after)->toBe($generated);
    expect($after)->toBe(round(capPay(240) * 2, 2));
});
