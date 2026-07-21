<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Check-out must be exactly as server-authoritative as check-in.
|--------------------------------------------------------------------------
|
| A prior version of this fix left checkIn() server-authoritative but kept
| checkOut() trusting a client captured_at within 48h/±5min as the
| effective check-out time. That asymmetry was itself exploitable: because
| checkOut() selects WHICH day's row to close from the effective time
| (`$targetDate = $checkOutTime->copy()->startOfDay()`), a backdated
| captured_at let an employee retroactively close a prior day's dangling
| check-in (including one already flagged by
| attendance:flag-missing-checkouts) and choose however many "worked"
| hours were most advantageous — turning a missed checkout into a clean,
| unflagged `present` day up to 48 hours later. Proven before this fix:
| check-in 2026-07-18 08:00, no real checkout, then on 2026-07-19 20:00 a
| checkout claiming captured_at=2026-07-18 18:00 returned HTTP 200 and
| persisted date=2026-07-18, check_out_time=2026-07-18 18:00,
| status=present, missing_checkout=false — 10 fabricated "worked" hours
| and a silently-cleared safety flag.
|
| The only direction this is exploitable is "claim I left later" (up to
| the real submission time) or "claim I left on an earlier, now-unverifiable
| day" — both strictly benefit the employee. "Claim I left earlier than I
| really did" only reduces credit, so it is not a realistic attack; the
| fix below closes both directions uniformly by never letting captured_at
| influence the effective time at all.
*/
uses(RefreshDatabase::class);

afterEach(function () {
    Carbon::setTestNow();
});

it('never lets a backdated captured_at retarget or fabricate a prior day from a missed checkout', function () {
    $employee = Employee::factory()->create();
    Sanctum::actingAs($employee->user);

    Carbon::setTestNow(Carbon::parse('2026-07-18 08:00:00', 'Asia/Jakarta'));
    postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
    ])->assertCreated();

    $danglingLog = AttendanceLog::sole();
    $danglingLog->update(['missing_checkout' => true]); // simulates the nightly flag job
    $statusBeforeExploitAttempt = $danglingLog->status;

    // 36 hours later — still inside the old 48h "trust window" — a client
    // claims captured_at = Day 1 18:00 (10h after the real check-in).
    Carbon::setTestNow(Carbon::parse('2026-07-19 20:00:00', 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-out', [
        'captured_at' => '2026-07-18T11:00:00.000Z', // 2026-07-18 18:00 WIB
    ]);

    // No check-in exists for "today" (2026-07-19) from the server's point
    // of view, so this must be rejected rather than silently reach back
    // and close 2026-07-18.
    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has not checked in for today.');

    // The rejected checkout attempt must leave the flagged record exactly
    // as HR's manual review would find it — untouched, not silently
    // "resolved" by the client's own backdated claim.
    $danglingLog->refresh();
    expect($danglingLog->date->toDateString())->toBe('2026-07-18')
        ->and($danglingLog->check_out_time)->toBeNull()
        ->and($danglingLog->missing_checkout)->toBeTrue()
        ->and($danglingLog->status)->toBe($statusBeforeExploitAttempt);

    expect(AttendanceLog::count())->toBe(1);
});

it('derives check-out time and status from the server clock, not a same-day captured_at claim', function () {
    $employee = Employee::factory()->create();
    Sanctum::actingAs($employee->user);

    Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'));
    postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
    ])->assertCreated();

    // Server clock has reached 14:00 (6h real elapsed => half_day), but the
    // client claims an earlier same-day capture (10:00, 2h). This claim was
    // inside the old 48h/±5min "trust window" and would have been used
    // verbatim as the effective check-out time pre-fix.
    Carbon::setTestNow(Carbon::parse('2026-07-20 14:00:00', 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-out', [
        'captured_at' => '2026-07-20T03:00:00.000Z', // 2026-07-20 10:00 WIB — claimed
    ]);

    $response->assertOk()
        ->assertJsonPath('data.check_out.time', '2026-07-20T14:00:00+07:00')
        ->assertJsonPath('data.check_out.captured_at', '2026-07-20T10:00:00+07:00')
        ->assertJsonPath('data.status', AttendanceLog::STATUS_HALF_DAY);

    $log = AttendanceLog::sole();
    expect($log->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 14:00:00')
        ->and($log->check_out_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-20 10:00:00')
        ->and($log->status)->toBe(AttendanceLog::STATUS_HALF_DAY);
});
