<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\AttendanceService;
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

describe('Attendance Timezone Tests', function () {
    it('stores check-in time in Jakarta timezone', function () {
        Sanctum::actingAs($this->employee->user);

        // Create a specific Jakarta time (11:43 AM)
        $jakartaTime = Carbon::create(2026, 1, 28, 11, 43, 0, 'Asia/Jakarta');

        // Mock Carbon::now to return our Jakarta time
        Carbon::setTestNow($jakartaTime);

        $response = postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);

        $response->assertCreated();

        $log = AttendanceLog::first();

        // The stored time should be in Jakarta timezone
        expect($log->check_in_time->format('H:i'))->toBe('11:43');
        expect($log->check_in_time->format('Y-m-d H:i:s'))->toBe('2026-01-28 11:43:00');

        // Verify the timezone is correctly set
        expect($log->check_in_time->timezone->getName())->toBe('Asia/Jakarta');

        Carbon::setTestNow();
    });

    it('stores check-out time in Jakarta timezone', function () {
        Sanctum::actingAs($this->employee->user);

        // Create check-in first
        $checkInTime = Carbon::create(2026, 1, 28, 9, 0, 0, 'Asia/Jakarta');
        Carbon::setTestNow($checkInTime);

        $attendanceLog = AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today('Asia/Jakarta'),
            'check_in_time' => $checkInTime,
            'check_out_time' => null,
        ]);

        // Mock checkout time (5:30 PM Jakarta time)
        $checkOutTime = Carbon::create(2026, 1, 28, 17, 30, 0, 'Asia/Jakarta');
        Carbon::setTestNow($checkOutTime);

        $response = postJson('/api/hris/attendance/check-out', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);

        $response->assertOk();

        $attendanceLog->refresh();

        // The stored checkout time should be in Jakarta timezone
        expect($attendanceLog->check_out_time->format('H:i'))->toBe('17:30');
        expect($attendanceLog->check_out_time->format('Y-m-d H:i:s'))->toBe('2026-01-28 17:30:00');

        // Verify the timezone is correctly set
        expect($attendanceLog->check_out_time->timezone->getName())->toBe('Asia/Jakarta');

        Carbon::setTestNow();
    });

    it('returns attendance times in Jakarta timezone via API', function () {
        Sanctum::actingAs($this->employee->user);

        // Create attendance with Jakarta time
        $jakartaCheckInTime = Carbon::create(2026, 1, 28, 11, 43, 0, 'Asia/Jakarta');

        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today('Asia/Jakarta'),
            'check_in_time' => $jakartaCheckInTime,
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data.check_in.time', $jakartaCheckInTime->toIso8601String());
    });

    it('calculates break duration from Jakarta wall-clock timestamps when the app timezone is UTC', function () {
        $originalAppTimezone = config('app.timezone');
        $originalPhpTimezone = date_default_timezone_get();

        try {
            config(['app.timezone' => 'UTC']);
            date_default_timezone_set('UTC');

            $breakTime = Carbon::create(2026, 7, 20, 11, 30, 0, 'Asia/Jakarta');
            Carbon::setTestNow($breakTime->copy()->utc());

            $attendanceLog = AttendanceLog::factory()->create([
                'employee_id' => $this->employee->id,
                'date' => $breakTime->toDateString(),
                'check_in_time' => $breakTime->copy()->subHours(2),
                'check_out_time' => null,
                'break_start_time' => $breakTime->format('Y-m-d H:i:s'),
                'break_end_time' => null,
                'total_break_minutes' => null,
            ]);

            app(AttendanceService::class)->endBreak($this->employee);

            $totalBreakMinutes = $attendanceLog->fresh()->total_break_minutes;
        } finally {
            Carbon::setTestNow();
            config(['app.timezone' => $originalAppTimezone]);
            date_default_timezone_set($originalPhpTimezone);
        }

        expect($totalBreakMinutes)->toBe(0);
    });
});
