<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\LateNotice;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 14:00:00', 'Asia/Jakarta'));

    $this->employee = Employee::factory()->create();
    Sanctum::actingAs($this->employee->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('never lets a trusted-window client captured_at improve check-in time or status, but still preserves it as evidence', function () {
    // Server clock is 2026-07-20 14:00 WIB (see beforeEach). A client
    // reports a captured_at of 08:00 the same day — well inside the old
    // 48h "trust window". The effective check-in time/status must come
    // from the server clock (14:00 => late), never from this claim; the
    // claim itself must still be persisted verbatim as evidence.
    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => '11111111-1111-4111-8111-111111111111',
        'accuracy_meters' => 12.5,
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.date', '2026-07-20')
        ->assertJsonPath('data.status', AttendanceLog::STATUS_LATE)
        ->assertJsonPath('data.check_in.time', '2026-07-20T14:00:00+07:00')
        ->assertJsonPath('data.check_in.captured_at', '2026-07-20T08:00:00+07:00')
        ->assertJsonPath('data.check_in.client_event_id', '11111111-1111-4111-8111-111111111111')
        ->assertJsonPath('data.check_in.accuracy_meters', '12.50')
        ->assertJsonPath('data.check_in.source_platform', 'native_android')
        ->assertJsonPath('data.check_in.location_provider', 'expo_location');

    $log = AttendanceLog::sole();

    expect($log->date->toDateString())->toBe('2026-07-20')
        ->and($log->status)->toBe(AttendanceLog::STATUS_LATE)
        ->and($log->check_in_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 14:00:00')
        ->and($log->check_in_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-20 08:00:00')
        ->and($log->check_in_client_event_id)->toBe('11111111-1111-4111-8111-111111111111')
        ->and($log->check_in_accuracy_meters)->toBe('12.50')
        ->and($log->check_in_source_platform)->toBe('native_android')
        ->and($log->check_in_location_provider)->toBe('expo_location');
});

it('never lets a stale client captured_at fabricate a prior day of attendance', function () {
    // Server clock is 2026-07-20 14:00 WIB. The client claims a captured_at
    // of yesterday 08:00 — inside the old 48h trust window. This must not
    // create (or target) a 2026-07-19 row; the date is always today.
    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-19T01:00:00.000Z',
        'client_event_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.date', '2026-07-20')
        ->assertJsonPath('data.status', AttendanceLog::STATUS_LATE)
        ->assertJsonPath('data.check_in.time', '2026-07-20T14:00:00+07:00')
        ->assertJsonPath('data.check_in.captured_at', '2026-07-19T08:00:00+07:00');

    $log = AttendanceLog::sole();

    expect($log->date->toDateString())->toBe('2026-07-20')
        ->and($log->check_in_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 14:00:00')
        ->and($log->check_in_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-19 08:00:00');
});

it('returns the same check-in for an exact client event replay', function () {
    $payload = [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => '22222222-2222-4222-8222-222222222222',
        'accuracy_meters' => 9.25,
        'source_platform' => 'native_ios',
        'location_provider' => 'expo_location',
    ];

    $first = postJson('/api/hris/attendance/check-in', $payload);
    $first->assertCreated();

    $replay = postJson('/api/hris/attendance/check-in', $payload);
    $replay->assertOk()
        ->assertJsonPath('message', 'Check-in already recorded.')
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.check_in.client_event_id', $payload['client_event_id']);

    expect(AttendanceLog::count())->toBe(1);
});

it('scopes client event replay to the authenticated employee', function () {
    $sharedEventId = '33333333-3333-4333-8333-333333333333';
    $payload = [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => $sharedEventId,
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ];

    $firstEmployeeResponse = postJson('/api/hris/attendance/check-in', $payload);
    $firstEmployeeResponse->assertCreated();

    $secondEmployee = Employee::factory()->create();
    Sanctum::actingAs($secondEmployee->user);

    $secondEmployeeResponse = postJson('/api/hris/attendance/check-in', $payload);
    $secondEmployeeResponse->assertCreated();

    expect($secondEmployeeResponse->json('data.id'))
        ->not->toBe($firstEmployeeResponse->json('data.id'))
        ->and($secondEmployeeResponse->json('data.employee_id'))->toBe($secondEmployee->id)
        ->and(AttendanceLog::count())->toBe(2);

    Sanctum::actingAs($this->employee->user);

    postJson('/api/hris/attendance/check-in', $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $firstEmployeeResponse->json('data.id'))
        ->assertJsonPath('data.employee_id', $this->employee->id);
});

it('closes today\'s check-in using server check-out time, keeps a differing captured_at as evidence, and replays idempotently', function () {
    // Check-out (like check-in) is fully server-authoritative: a client
    // can no longer retarget a prior day's dangling check-in via
    // captured_at (see AttendanceCheckoutServerAuthorityTest for the
    // proven cross-day exploit this closes). This test therefore stays
    // within the same server day and asserts check_out.time (18:00,
    // server) differs from check_out.captured_at (11:00, the client's
    // claim) — the claim is stored evidence only.
    $checkIn = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => '44444444-4444-4444-8444-444444444444',
        'accuracy_meters' => 8.5,
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ]);
    $checkIn->assertCreated();

    Carbon::setTestNow(Carbon::parse('2026-07-20 18:00:00', 'Asia/Jakarta'));

    $payload = [
        'captured_at' => '2026-07-20T04:00:00.000Z', // 2026-07-20 11:00 WIB — evidence only
        'client_event_id' => '55555555-5555-4555-8555-555555555555',
        'accuracy_meters' => 7.75,
        'source_platform' => 'mobile_web',
        'location_provider' => 'browser_geolocation',
    ];

    $firstCheckout = postJson('/api/hris/attendance/check-out', $payload);
    $firstCheckout->assertOk()
        ->assertJsonPath('data.id', $checkIn->json('data.id'))
        ->assertJsonPath('data.date', '2026-07-20')
        ->assertJsonPath('data.check_out.time', '2026-07-20T18:00:00+07:00')
        ->assertJsonPath('data.check_out.captured_at', '2026-07-20T11:00:00+07:00')
        ->assertJsonPath('data.check_out.client_event_id', $payload['client_event_id'])
        ->assertJsonPath('data.check_out.accuracy_meters', '7.75')
        ->assertJsonPath('data.check_out.source_platform', 'mobile_web')
        ->assertJsonPath('data.check_out.location_provider', 'browser_geolocation');

    $replay = postJson('/api/hris/attendance/check-out', $payload);
    $replay->assertOk()
        ->assertJsonPath('data.id', $firstCheckout->json('data.id'))
        ->assertJsonPath('data.check_out.client_event_id', $payload['client_event_id']);

    $log = AttendanceLog::sole();

    expect($log->date->toDateString())->toBe('2026-07-20')
        ->and($log->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 18:00:00')
        ->and($log->check_out_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-20 11:00:00')
        ->and($log->check_out_client_event_id)->toBe($payload['client_event_id'])
        ->and($log->check_out_accuracy_meters)->toBe('7.75')
        ->and($log->check_out_source_platform)->toBe('mobile_web')
        ->and($log->check_out_location_provider)->toBe('browser_geolocation')
        ->and(AttendanceLog::count())->toBe(1);
});

it('validates check-in evidence instead of silently discarding it', function () {
    postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => 'not-a-date',
        'client_event_id' => str_repeat('x', 65),
        'accuracy_meters' => -0.01,
        'source_platform' => 'native_windows',
        'location_provider' => 'gps',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'captured_at',
            'client_event_id',
            'accuracy_meters',
            'source_platform',
            'location_provider',
        ]);
});

it('validates check-out evidence instead of silently discarding it', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
    ]);

    postJson('/api/hris/attendance/check-out', [
        'captured_at' => 'not-a-date',
        'client_event_id' => str_repeat('x', 65),
        'accuracy_meters' => -0.01,
        'source_platform' => 'native_windows',
        'location_provider' => 'gps',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'captured_at',
            'client_event_id',
            'accuracy_meters',
            'source_platform',
            'location_provider',
        ]);
});

it('preserves an untrusted reported captured time but uses server time for attendance calculations', function () {
    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-10T01:00:00.000Z',
        'client_event_id' => '66666666-6666-4666-8666-666666666666',
        'source_platform' => 'desktop_web',
        'location_provider' => 'browser_geolocation',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.date', '2026-07-20')
        ->assertJsonPath('data.status', AttendanceLog::STATUS_LATE)
        ->assertJsonPath('data.check_in.time', '2026-07-20T14:00:00+07:00')
        ->assertJsonPath('data.check_in.captured_at', '2026-07-10T08:00:00+07:00');

    $log = AttendanceLog::sole();

    expect($log->date->toDateString())->toBe('2026-07-20')
        ->and($log->status)->toBe(AttendanceLog::STATUS_LATE)
        ->and($log->check_in_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 14:00:00')
        ->and($log->check_in_captured_at->format('Y-m-d H:i:s'))->toBe('2026-07-10 08:00:00');
});

it('finalizes late status and notice before a check-in becomes replayable', function () {
    $payload = [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T04:00:00.000Z',
        'client_event_id' => '77777777-7777-4777-8777-777777777777',
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ];

    $created = app(AttendanceService::class)->checkIn($this->employee, $payload);
    $replayed = app(AttendanceService::class)->checkIn($this->employee, $payload);

    expect($created->fresh()->status)->toBe(AttendanceLog::STATUS_LATE)
        ->and($replayed->id)->toBe($created->id)
        ->and($replayed->status)->toBe(AttendanceLog::STATUS_LATE)
        ->and(AttendanceLog::count())->toBe(1)
        ->and(LateNotice::where('attendance_log_id', $created->id)->count())->toBe(1);
});

it('closes an open break using the server check-out time', function () {
    postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => '88888888-8888-4888-8888-888888888888',
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ])->assertCreated();

    AttendanceLog::sole()->update([
        'break_start_time' => '2026-07-20 15:00:00',
        'break_end_time' => null,
        'total_break_minutes' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-20 19:00:00', 'Asia/Jakarta'));

    postJson('/api/hris/attendance/check-out', [
        'client_event_id' => '99999999-9999-4999-8999-999999999999',
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ])->assertOk()
        ->assertJsonPath('data.break.end_time', '2026-07-20T19:00:00+07:00')
        ->assertJsonPath('data.break.total_minutes', 240);

    $log = AttendanceLog::sole();

    expect($log->break_end_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 19:00:00')
        ->and($log->total_break_minutes)->toBe(240)
        ->and($log->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-07-20 19:00:00');
});

it('persists checkout status derived from the server check-out time and preserves it on replay', function () {
    postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
        'captured_at' => '2026-07-20T01:00:00.000Z',
        'client_event_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'source_platform' => 'native_android',
        'location_provider' => 'expo_location',
    ])->assertCreated();

    // Check-in effective time is server 14:00 WIB (see beforeEach). Advance
    // to 18:00 for a 4h day => half_day. The captured_at claim below
    // (11:00, before check-in) is evidence only and must not affect the
    // effective check-out time or the derived status.
    Carbon::setTestNow(Carbon::parse('2026-07-20 18:00:00', 'Asia/Jakarta'));

    $payload = [
        'captured_at' => '2026-07-20T04:00:00.000Z',
        'client_event_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        'source_platform' => 'mobile_web',
        'location_provider' => 'browser_geolocation',
    ];

    postJson('/api/hris/attendance/check-out', $payload)
        ->assertOk()
        ->assertJsonPath('data.status', AttendanceLog::STATUS_HALF_DAY);

    expect(AttendanceLog::sole()->status)->toBe(AttendanceLog::STATUS_HALF_DAY);

    postJson('/api/hris/attendance/check-out', $payload)
        ->assertOk()
        ->assertJsonPath('data.status', AttendanceLog::STATUS_HALF_DAY);
});

it('resolves a same-event check-in when the initial replay lookup misses a concurrent commit', function () {
    $eventId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    $existing = AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
        'check_in_client_event_id' => $eventId,
    ]);

    $service = new class extends AttendanceService
    {
        protected function findEventReplay(Employee $employee, string $column, string $eventId): ?AttendanceLog
        {
            return null;
        }
    };

    $replayed = $service->checkIn($this->employee, [
        'mode' => AttendanceLog::MODE_OFFICE,
        'client_event_id' => $eventId,
    ]);

    expect($replayed->id)->toBe($existing->id)
        ->and(AttendanceLog::count())->toBe(1);
});

it('resolves a same-event checkout when the initial replay lookup misses a concurrent commit', function () {
    $eventId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    $existing = AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
        'check_out_time' => '2026-07-20 17:00:00',
        'check_out_client_event_id' => $eventId,
    ]);

    $service = new class extends AttendanceService
    {
        protected function findEventReplay(Employee $employee, string $column, string $eventId): ?AttendanceLog
        {
            return null;
        }
    };

    $replayed = $service->checkOut($this->employee, [
        'client_event_id' => $eventId,
    ]);

    expect($replayed->id)->toBe($existing->id)
        ->and(AttendanceLog::count())->toBe(1);
});
