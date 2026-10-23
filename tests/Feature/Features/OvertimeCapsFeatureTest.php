<?php

use App\Facades\Features;
use App\Features\Feature;
use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\OvertimePayService;
use Carbon\Carbon;

// Mon 2026-04-06 → Sun 2026-04-12, the same week OvertimeStatutoryCapTest uses.

function uncappedEmployee(): array
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

function uncappedLog(User $user, string $date, int $minutes): OvertimeLog
{
    $start = Carbon::parse($date)->setTime(18, 0);

    return OvertimeLog::create([
        'user_id' => $user->id,
        'manager_id' => User::factory()->create()->id,
        'task_name' => "OT {$date}",
        'start_time' => $start,
        'end_time' => $start->copy()->addMinutes($minutes),
        'duration_minutes' => $minutes,
        'hourly_rate' => 1,
        'status' => OvertimeLog::STATUS_APPROVED,
    ]);
}

function uncappedBatch()
{
    return app(OvertimePayService::class)->generateBatch(
        Carbon::parse('2026-04-06'),
        Carbon::parse('2026-04-12'),
        Carbon::parse('2026-04-14'),
    );
}

it('caps a long session at 4 hours when the caps are on', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => true], 'test');

    $log = new OvertimeLog(['duration_minutes' => 382]);

    expect($log->payableMinutes())->toBe(240)
        ->and($log->isCapped())->toBeTrue();
});

it('pays every approved minute when the caps are off', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => false], 'test');

    $log = new OvertimeLog(['duration_minutes' => 382]);

    expect($log->payableMinutes())->toBe(382)
        ->and($log->isCapped())->toBeFalse();
});

it('pays past the daily and weekly ceilings in a batch when the caps are off', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => false], 'test');

    [$user, $employee] = uncappedEmployee();

    // Five weekdays of 5h each: 25h, over both the 4h/day and the 18h/week ceiling.
    foreach (['2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10'] as $day) {
        uncappedLog($user, $day, 300);
    }

    $payment = uncappedBatch()->payments()->where('employee_id', $employee->id)->first();

    $sessions = collect($payment->sessions_breakdown);
    expect($sessions)->toHaveCount(5)
        ->and($sessions->sum('payable_minutes'))->toBe(1500)
        ->and($sessions->sum('excess_minutes'))->toBe(0);
});

it('still applies both ceilings in a batch when the caps are on', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => true], 'test');

    [$user, $employee] = uncappedEmployee();

    foreach (['2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10'] as $day) {
        uncappedLog($user, $day, 300);
    }

    $payment = uncappedBatch()->payments()->where('employee_id', $employee->id)->first();

    // 4h/day for four days hits 16h, the fifth day gets the 2h left of the 18h week.
    expect(collect($payment->sessions_breakdown)->sum('payable_minutes'))->toBe(1080);
});
