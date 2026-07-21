<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Check-out time ordering vs check-in time.
|--------------------------------------------------------------------------
|
| Check-out is now fully server-authoritative (see
| AttendanceCheckoutServerAuthorityTest), so a client can no longer move
| the effective check-out time via captured_at, and — since check-in is
| also always server time — check_in_time can never be later than "now"
| at check-out through the normal API flow alone. This guard remains as a
| data-integrity safeguard against check_in_time values that reach the
| database some other way (a Filament admin correction, a bulk/legacy
| import, or a multi-server clock-skew edge case), so a corrupted record
| still fails safely (422) instead of persisting a negative duration.
*/
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create();
    Sanctum::actingAs($this->employee->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('rejects a check-out reported earlier than check-in with a 422, not a 500', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 12:00:00',
    ]);

    // Simulates a check_in_time that reached the database ahead of "now"
    // (e.g. a Filament correction or import), not a client claim.
    Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Check-out time cannot be earlier than check-in time.');

    $log = AttendanceLog::sole();

    expect($log->check_out_time)->toBeNull()
        ->and($log->status)->not->toBe(AttendanceLog::STATUS_HALF_DAY);
});

it('never persists a negative worked-minutes attendance record', function () {
    $attendanceLog = AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 12:00:00',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'));

    postJson('/api/hris/attendance/check-out', [])->assertStatus(422);

    $attendanceLog->refresh();

    // Worked minutes are only ever computed downstream from a persisted
    // check_out_time; asserting it stayed null is the strongest guarantee
    // that no negative duration can ever be derived from this record.
    expect($attendanceLog->check_out_time)->toBeNull();
});

it('allows a check-out reported at the exact same instant as check-in', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 12:00:00',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-20 12:00:00', 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertOk();

    expect(AttendanceLog::sole()->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 12:00:00');
});

it('ignores a captured_at claim entirely when ordering check-out against check-in', function () {
    // Even a captured_at claiming a time before check-in must not trigger
    // the ordering guard, because captured_at no longer drives the
    // effective check-out time at all — only the server clock does.
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 12:00:00',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-20 18:00:00', 'Asia/Jakarta'));

    $response = postJson('/api/hris/attendance/check-out', [
        'captured_at' => '2026-07-20T01:00:00.000Z', // 2026-07-20 08:00 WIB — before check-in
    ]);

    $response->assertOk();

    $log = AttendanceLog::sole();
    expect($log->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 18:00:00')
        ->and($log->check_out_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-20 08:00:00');
});
