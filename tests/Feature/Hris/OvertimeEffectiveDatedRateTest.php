<?php

use App\Exceptions\Hris\OvertimeBatchLockedException;
use App\Exceptions\Hris\OvertimeRateCoverageException;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\OvertimePayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Week under test: Mon 2026-04-06 → Sun 2026-04-12 (Sat 04-11, Sun 04-12)
const OT_WEEK_START = '2026-04-06';
const OT_WEEK_END = '2026-04-12';
const OT_PAYMENT_DT = '2026-04-14';

// round(basic / 173, 2)
const RATE_5M = 28901.73;
const RATE_8M = 46242.77;
const RATE_3M = 17341.04;

function otEmployee(): array
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    return [$user, $employee];
}

function otStructure(Employee $employee, string $effectiveDate, float $basic, float $dailyRate = 50_000): SalaryStructure
{
    return SalaryStructure::create([
        'employee_id' => $employee->id,
        'effective_date' => $effectiveDate,
        'basic_salary' => $basic,
        'daily_allowance_rate' => $dailyRate,
        'prorate_enabled' => false,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ]);
}

/** Frozen rate is deliberately wrong so any test that passes proves re-resolution. */
function otLog(User $user, string $date, int $minutes = 120, float $frozenRate = 999_999): OvertimeLog
{
    return OvertimeLog::create([
        'user_id' => $user->id,
        'manager_id' => User::factory()->create()->id,
        'task_name' => "OT {$date}",
        'start_time' => Carbon::parse("{$date} 18:00"),
        'end_time' => Carbon::parse("{$date} 18:00")->addMinutes($minutes),
        'duration_minutes' => $minutes,
        'hourly_rate' => $frozenRate,
        'status' => OvertimeLog::STATUS_APPROVED,
    ]);
}

function otGenerate(): OvertimePayBatch
{
    return app(OvertimePayService::class)->generateBatch(
        Carbon::parse(OT_WEEK_START),
        Carbon::parse(OT_WEEK_END),
        Carbon::parse(OT_PAYMENT_DT),
    );
}

/**
 * 2h = 1.5x first hour + 2.0x second hour. Mirrors OvertimeLog::calculateEstimatedPay()
 * term-by-term — folding it to 3.5x rate lands on the other side of a rounding boundary.
 */
function otPayFor(float $rate): float
{
    return round($rate * 1.5 + $rate * 2.0 * 1.0, 2);
}

function otSessions(OvertimePayBatch $batch, Employee $employee): array
{
    return $batch->payments()->where('employee_id', $employee->id)->first()->sessions_breakdown;
}

// ---------------------------------------------------------------------------

describe('overtime rate resolves against the structure effective on the work date', function () {

    it('MANDATORY: pays a backdated salary INCREASE entered after the overtime was logged', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);

        $log = otLog($user, '2026-04-08');

        // HR enters the raise later, backdated to the 1st — after the log already exists.
        otStructure($employee, '2026-04-01', 8_000_000);

        // A LATER raise, effective after the work date but before "today". Without this
        // the backdated structure is also the newest-as-of-today, so an as-of-today
        // implementation would coincidentally return the right answer and pass.
        otStructure($employee, '2026-06-01', 12_000_000);

        $sessions = otSessions(otGenerate(), $employee);

        expect($sessions)->toHaveCount(1)
            ->and($sessions[0]['hourly_rate'])->toBe(RATE_8M)
            ->and($sessions[0]['pay'])->toEqualWithDelta(otPayFor(RATE_8M), 0.01);

        // The log is re-stamped with what was actually paid.
        expect((float) $log->fresh()->hourly_rate)->toBe(RATE_8M);
    });

    it('pays a backdated salary DECREASE entered after the overtime was logged', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 8_000_000);

        otLog($user, '2026-04-08');

        otStructure($employee, '2026-04-01', 3_000_000);

        // Later change, effective after the work date — see the INCREASE test.
        otStructure($employee, '2026-06-01', 10_000_000);

        $sessions = otSessions(otGenerate(), $employee);

        expect($sessions[0]['hourly_rate'])->toBe(RATE_3M)
            ->and($sessions[0]['pay'])->toEqualWithDelta(otPayFor(RATE_3M), 0.01);
    });

    it('prices each log in one batch from its own effective structure', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otStructure($employee, '2026-04-09', 8_000_000);

        otLog($user, '2026-04-07'); // before the change
        otLog($user, '2026-04-10'); // after the change

        $byDate = collect(otSessions(otGenerate(), $employee))
            ->keyBy(fn ($s) => Carbon::parse($s['start_time'])->toDateString());

        expect($byDate)->toHaveCount(2)
            ->and($byDate['2026-04-07']['hourly_rate'])->toBe(RATE_5M)
            ->and($byDate['2026-04-10']['hourly_rate'])->toBe(RATE_8M);
    });

    it('uses the new structure for a log starting exactly on the effective date', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otStructure($employee, '2026-04-08', 8_000_000);

        otLog($user, '2026-04-08');

        expect(otSessions(otGenerate(), $employee)[0]['hourly_rate'])->toBe(RATE_8M);
    });

    it('ignores a structure effective after the work date', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otStructure($employee, '2026-04-20', 8_000_000); // future relative to the log

        otLog($user, '2026-04-08');

        expect(otSessions(otGenerate(), $employee)[0]['hourly_rate'])->toBe(RATE_5M);
    });

    it('excludes fixed allowances from the rate — basic salary only', function () {
        [$user, $employee] = otEmployee();

        // 5,000,000 basic + 1,000,000 fixed. The rate must ignore the allowance:
        // 5,000,000/173 = 28,901.73, NOT 6,000,000/173 = 34,682.08.
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2026-01-01',
            'basic_salary' => 5_000_000,
            'allowances_fixed' => [
                ['type' => 'position', 'name' => 'Tunjangan Jabatan', 'amount' => 1_000_000],
            ],
            'daily_allowance_rate' => 50_000,
            'prorate_enabled' => false,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        otLog($user, '2026-04-08');

        expect(otSessions(otGenerate(), $employee)[0]['hourly_rate'])->toBe(RATE_5M);
    });

    it('never trusts the frozen hourly_rate on the log', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);

        otLog($user, '2026-04-08', 120, 750_000); // absurd frozen rate

        expect(otSessions(otGenerate(), $employee)[0]['hourly_rate'])->toBe(RATE_5M);
    });
});

describe('missing salary structure', function () {

    it('fails loudly instead of paying Rp 0 when no structure covers the work date', function () {
        [$user] = otEmployee();
        otLog($user, '2026-04-08');

        expect(fn () => otGenerate())->toThrow(OvertimeRateCoverageException::class);
    });

    it('persists no batch and no zero-value payment when resolution fails', function () {
        [$user] = otEmployee();
        otLog($user, '2026-04-08');

        try {
            otGenerate();
        } catch (OvertimeRateCoverageException $e) {
            // expected
        }

        expect(OvertimePayBatch::count())->toBe(0);
    });

    it('fails loudly when the covering structure has a zero basic salary', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 0); // structure exists, but pays nothing

        otLog($user, '2026-04-08');

        expect(fn () => otGenerate())->toThrow(OvertimeRateCoverageException::class);
        expect(OvertimePayBatch::count())->toBe(0);
    });

    it('reports every uncovered log at once', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-04-09', 5_000_000); // starts mid-week

        otLog($user, '2026-04-07'); // uncovered
        otLog($user, '2026-04-08'); // uncovered
        otLog($user, '2026-04-10'); // covered

        try {
            otGenerate();
            $this->fail('Expected OvertimeRateCoverageException');
        } catch (OvertimeRateCoverageException $e) {
            expect($e->gaps)->toHaveCount(2)
                ->and(collect($e->gaps)->pluck('work_date')->all())
                ->toBe(['2026-04-07', '2026-04-08']);
        }
    });
});

describe('correcting an existing batch', function () {

    it('recalculates an unpaid batch after a backdated raise', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();
        expect(otSessions($batch, $employee)[0]['hourly_rate'])->toBe(RATE_5M);

        // Backdated raise arrives after the batch was generated.
        otStructure($employee, '2026-04-01', 8_000_000);

        $corrected = app(OvertimePayService::class)->recalculateBatch($batch);
        $sessions = otSessions($corrected, $employee);

        expect($sessions[0]['hourly_rate'])->toBe(RATE_8M)
            ->and($sessions[0]['pay'])->toEqualWithDelta(otPayFor(RATE_8M), 0.01)
            ->and((float) $corrected->total_amount)->toEqualWithDelta(otPayFor(RATE_8M), 0.01);
    });

    it('keeps the batch identity, approval history and audit trail', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();

        // Stamp an approval/audit history, then reject so the batch is unlocked
        // but still carries its trail.
        $approver = User::factory()->create();
        $batch->update([
            'status' => OvertimePayBatch::STATUS_REJECTED,
            'submitted_by' => $user->id,
            'submitted_at' => Carbon::parse('2026-04-14 09:00'),
            'rejection_reason' => 'Rate looked wrong',
            'generated_by' => $approver->id,
            'generated_at' => Carbon::parse('2026-04-13 08:00'),
        ]);

        otStructure($employee, '2026-04-01', 8_000_000);

        $corrected = app(OvertimePayService::class)->recalculateBatch($batch);

        expect($corrected->id)->toBe($batch->id)
            ->and(OvertimePayBatch::count())->toBe(1)
            // approval history and audit trail survive the correction
            ->and($corrected->submitted_by)->toBe($user->id)
            ->and($corrected->submitted_at->toDateTimeString())->toBe('2026-04-14 09:00:00')
            ->and($corrected->rejection_reason)->toBe('Rate looked wrong')
            ->and($corrected->generated_by)->toBe($approver->id)
            ->and($corrected->generated_at->toDateTimeString())->toBe('2026-04-13 08:00:00')
            ->and($corrected->status)->toBe(OvertimePayBatch::STATUS_REJECTED)
            // and the money was actually corrected
            ->and(otSessions($corrected, $employee)[0]['hourly_rate'])->toBe(RATE_8M);
    });

    it('recalculation is idempotent', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();
        otStructure($employee, '2026-04-01', 8_000_000);

        $service = app(OvertimePayService::class);
        $first = (float) $service->recalculateBatch($batch)->total_amount;
        $second = (float) $service->recalculateBatch($batch->fresh())->total_amount;

        expect($second)->toBe($first);
    });

    it('refuses to recalculate a PAID batch', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();
        $before = (float) $batch->payments()->first()->total_amount;

        otStructure($employee, '2026-04-01', 8_000_000);
        $batch->update(['status' => OvertimePayBatch::STATUS_PAID]);

        expect(fn () => app(OvertimePayService::class)->recalculateBatch($batch))
            ->toThrow(OvertimeBatchLockedException::class);

        expect((float) $batch->payments()->first()->total_amount)->toBe($before)
            ->and($before)->toEqualWithDelta(otPayFor(RATE_5M), 0.01);
    });

    it('refuses to recalculate a batch locked in finance approval', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();
        $batch->update(['status' => OvertimePayBatch::STATUS_PENDING_FINANCE]);

        otStructure($employee, '2026-04-01', 8_000_000);

        expect(fn () => app(OvertimePayService::class)->recalculateBatch($batch))
            ->toThrow(OvertimeBatchLockedException::class);

        expect(otSessions($batch->fresh(), $employee)[0]['hourly_rate'])->toBe(RATE_5M);
    });

    it('leaves the batch untouched when recalculation hits an uncovered log', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        $batch = otGenerate();
        $paymentBefore = (float) $batch->payments()->first()->total_amount;
        $batchBefore = (float) $batch->total_amount;

        // Structure removed after generation — recalculation can no longer resolve.
        SalaryStructure::where('employee_id', $employee->id)->delete();

        expect(fn () => app(OvertimePayService::class)->recalculateBatch($batch))
            ->toThrow(OvertimeRateCoverageException::class);

        expect(otSessions($batch->fresh(), $employee)[0]['hourly_rate'])->toBe(RATE_5M);

        // The gap path skips the session write, so hourly_rate alone proves nothing.
        // total_amount is what a non-atomic recalculation zeroes before throwing.
        expect((float) $batch->fresh()->payments()->first()->total_amount)->toBe($paymentBefore)
            ->and((float) $batch->fresh()->total_amount)->toBe($batchBefore)
            ->and($paymentBefore)->toBeGreaterThan(0.0);
    });
});

describe('generation, --force and recalculation agree', function () {

    it('prices identically whether reached by generate, --force regenerate, or recalculate', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        // 1. plain generation, then a backdated raise
        $batch = otGenerate();
        otStructure($employee, '2026-04-01', 8_000_000);

        // 2. recalculate in place
        $recalculated = (float) app(OvertimePayService::class)->recalculateBatch($batch)->total_amount;

        // 3. --force regenerate from scratch over the same week
        $batch->payments()->each(fn ($p) => $p->overtimeLogs()->update(['overtime_payment_id' => null]));
        $batch->payments()->delete();
        $batch->delete();
        $regenerated = (float) otGenerate()->total_amount;

        expect($recalculated)->toBe($regenerated)
            ->and($recalculated)->toEqualWithDelta(otPayFor(RATE_8M), 0.01);
    });

    it('prices identically via recalculate and --force regenerate when the week has BOTH an overtime log and a weekend attendance day, and the backdated structure changes basic salary AND the daily allowance rate', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000, 50_000);

        $log = otLog($user, '2026-04-08'); // Wednesday overtime

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        // 1. plain generation, then a backdated raise touching BOTH rates
        $batch = otGenerate();
        otStructure($employee, '2026-04-01', 8_000_000, 90_000);

        // 2. recalculate in place
        $recalculated = app(OvertimePayService::class)->recalculateBatch($batch);
        $recalculatedSessions = collect(otSessions($recalculated, $employee));
        $recalculatedOt = $recalculatedSessions->firstWhere('overtime_log_id', $log->id);
        $recalculatedWeekend = $recalculatedSessions->firstWhere('session_type', 'weekend_attendance');

        // 3. --force regenerate from scratch over the same week
        $batch->payments()->each(fn ($p) => $p->overtimeLogs()->update(['overtime_payment_id' => null]));
        $batch->payments()->delete();
        $batch->delete();
        $regeneratedBatch = otGenerate();
        $regeneratedSessions = collect(otSessions($regeneratedBatch, $employee));
        $regeneratedOt = $regeneratedSessions->firstWhere('overtime_log_id', $log->id);
        $regeneratedWeekend = $regeneratedSessions->firstWhere('session_type', 'weekend_attendance');

        // Concrete amounts, independently derived (not just cross-path equality),
        // so the test still fails if both paths drift together.
        $expectedOtPay = otPayFor(RATE_8M);
        $expectedWeekendPay = 90_000.0;

        expect($recalculatedOt['hourly_rate'])->toBe(RATE_8M)
            ->and((float) $recalculatedOt['pay'])->toEqualWithDelta($expectedOtPay, 0.01)
            ->and((float) $recalculatedWeekend['pay'])->toBe($expectedWeekendPay)
            ->and((float) $recalculated->total_amount)->toEqualWithDelta($expectedOtPay + $expectedWeekendPay, 0.01);

        expect($regeneratedOt['hourly_rate'])->toBe(RATE_8M)
            ->and((float) $regeneratedOt['pay'])->toEqualWithDelta($expectedOtPay, 0.01)
            ->and((float) $regeneratedWeekend['pay'])->toBe($expectedWeekendPay)
            ->and((float) $regeneratedBatch->total_amount)->toEqualWithDelta($expectedOtPay + $expectedWeekendPay, 0.01);

        expect((float) $recalculated->total_amount)->toBe((float) $regeneratedBatch->total_amount);
    });

    it('refuses --force regeneration of a locked batch', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000);
        otLog($user, '2026-04-08');

        otGenerate()->update(['status' => OvertimePayBatch::STATUS_PAID]);

        $this->artisan('overtime-pay:generate', [
            '--week-start' => OT_WEEK_START,
            '--force' => true,
        ])->assertFailed();

        expect(OvertimePayBatch::count())->toBe(1)
            ->and(OvertimePayBatch::first()->status)->toBe(OvertimePayBatch::STATUS_PAID);
    });
});

describe('weekday and weekend resolve from the same structure', function () {

    it('prices a weekday OT log and a same-week weekend attendance from each date\'s structure', function () {
        [$user, $employee] = otEmployee();
        otStructure($employee, '2026-01-01', 5_000_000, 50_000);
        otStructure($employee, '2026-04-11', 8_000_000, 90_000); // effective on the Saturday

        otLog($user, '2026-04-10');  // Friday — old structure
        otLog($user, '2026-04-11');  // Saturday — new structure

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-11', // Saturday
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => '2026-04-11 08:00:00',
        ]);

        $sessions = collect(otSessions(otGenerate(), $employee));

        $friday = $sessions->firstWhere('task_name', 'OT 2026-04-10');
        $saturday = $sessions->firstWhere('task_name', 'OT 2026-04-11');
        $weekend = $sessions->firstWhere('session_type', 'weekend_attendance');

        expect($friday['hourly_rate'])->toBe(RATE_5M)
            ->and($saturday['hourly_rate'])->toBe(RATE_8M)
            // Same day, same structure: the Saturday OT rate and the Saturday
            // daily allowance both come from the 2026-04-11 structure.
            ->and((float) $weekend['pay'])->toBe(90_000.0);
    });
});
