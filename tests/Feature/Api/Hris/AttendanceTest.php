<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);
});

describe('Check-in endpoint', function () {
    it('records check-in successfully', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'employee_id',
                    'date',
                    'mode',
                    'source',
                    'status',
                    'check_in' => ['time', 'latitude', 'longitude', 'address', 'photo_path'],
                    'check_out' => ['time', 'latitude', 'longitude', 'address', 'photo_path'],
                ],
            ]);

        expect(AttendanceLog::count())->toBe(1);
        $log = AttendanceLog::first();
        expect($log->employee_id)->toBe($this->employee->id);
        expect($log->mode)->toBe(AttendanceLog::MODE_OFFICE);
        expect($log->source)->toBe(AttendanceLog::SOURCE_MOBILE);
    });

    it('requires authentication', function () {
        $response = postJson('/api/hris/attendance/check-in', [
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertUnauthorized();
    });

    it('rejects duplicate check-in', function () {
        Sanctum::actingAs($this->employee->user);

        // First check-in
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today(),
            'check_in_time' => Carbon::now(),
        ]);

        $response = postJson('/api/hris/attendance/check-in', [
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Employee has already checked in for today.');
    });

    it('validates GPS coordinates range', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => 100, // Invalid: must be between -90 and 90
            'longitude' => 200, // Invalid: must be between -180 and 180
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    });

    it('validates mode values', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-in', [
            'mode' => 'invalid_mode',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mode']);
    });
});


describe('Check-out endpoint', function () {
    it('records check-out successfully', function () {
        Sanctum::actingAs($this->employee->user);

        // Create check-in first
        $attendanceLog = AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today(),
            'check_in_time' => Carbon::now()->subHours(8),
            'check_out_time' => null,
        ]);

        $response = postJson('/api/hris/attendance/check-out', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'check_out' => ['time', 'latitude', 'longitude'],
                ],
            ]);

        $attendanceLog->refresh();
        expect($attendanceLog->check_out_time)->not->toBeNull();
        expect((float) $attendanceLog->check_out_latitude)->toBe(-6.2088);
    });

    it('requires authentication', function () {
        $response = postJson('/api/hris/attendance/check-out', []);

        $response->assertUnauthorized();
    });

    it('rejects check-out without check-in', function () {
        Sanctum::actingAs($this->employee->user);

        $response = postJson('/api/hris/attendance/check-out', []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Employee has not checked in for today.');
    });
});

describe('Daily attendance endpoint', function () {
    it('returns daily attendance', function () {
        Sanctum::actingAs($this->employee->user);

        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today(),
            'check_in_time' => Carbon::now()->setTime(9, 0),
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'date',
                    'status',
                    'check_in',
                ],
            ]);
    });

    it('returns null when no attendance record', function () {
        Sanctum::actingAs($this->employee->user);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data', null);
    });

    it('accepts date parameter', function () {
        Sanctum::actingAs($this->employee->user);

        $yesterday = Carbon::yesterday();
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $yesterday,
            'check_in_time' => $yesterday->copy()->setTime(9, 0),
        ]);

        $response = getJson('/api/hris/attendance/daily?date=' . $yesterday->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonPath('data.date', $yesterday->format('Y-m-d'));
    });

    it('requires authentication', function () {
        $response = getJson('/api/hris/attendance/daily');

        $response->assertUnauthorized();
    });
});

describe('History endpoint', function () {
    it('returns attendance history within date range', function () {
        Sanctum::actingAs($this->employee->user);

        // Create attendance logs for the past week
        for ($i = 0; $i < 5; $i++) {
            $date = Carbon::today()->subDays($i);
            AttendanceLog::factory()->create([
                'employee_id' => $this->employee->id,
                'date' => $date,
                'check_in_time' => $date->copy()->setTime(9, 0),
            ]);
        }

        $from = Carbon::today()->subDays(4)->format('Y-m-d');
        $to = Carbon::today()->format('Y-m-d');

        $response = getJson("/api/hris/attendance/history?from={$from}&to={$to}");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('requires from and to parameters', function () {
        Sanctum::actingAs($this->employee->user);

        $response = getJson('/api/hris/attendance/history');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    });

    it('validates to date is after from date', function () {
        Sanctum::actingAs($this->employee->user);

        $response = getJson('/api/hris/attendance/history?from=2024-01-10&to=2024-01-05');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    });

    it('requires authentication', function () {
        $response = getJson('/api/hris/attendance/history?from=2024-01-01&to=2024-01-31');

        $response->assertUnauthorized();
    });
});

describe('WFH quota endpoint', function () {
    it('returns remaining WFH quota', function () {
        Sanctum::actingAs($this->employee->user);

        $response = getJson('/api/hris/attendance/wfh-quota');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'remaining_quota',
                    'max_quota_per_week',
                ],
            ])
            ->assertJsonPath('data.remaining_quota', 1)
            ->assertJsonPath('data.max_quota_per_week', AttendanceSetting::getSettings()->wfh_quota_per_week);
    });

    it('returns zero quota when already used', function () {
        Sanctum::actingAs($this->employee->user);

        // Use WFH quota for this week
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $monday,
            'mode' => AttendanceLog::MODE_WFH,
            'check_in_time' => $monday->copy()->setTime(9, 0),
        ]);

        $response = getJson('/api/hris/attendance/wfh-quota');

        $response->assertOk()
            ->assertJsonPath('data.remaining_quota', 0);
    });

    it('requires authentication', function () {
        $response = getJson('/api/hris/attendance/wfh-quota');

        $response->assertUnauthorized();
    });
});
