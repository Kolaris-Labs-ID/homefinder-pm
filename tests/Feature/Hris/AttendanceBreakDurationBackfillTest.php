<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ATTENDANCE_BREAK_DURATION_BACKFILL = 'database/migrations/2026_07_20_000001_backfill_negative_attendance_break_minutes.php';

it('backfills only negative break minutes and is idempotent', function () {
    $employee = Employee::factory()->create();
    $breakStart = Carbon::create(2026, 7, 20, 11, 30, 0, 'Asia/Jakarta');
    $breakEnd = $breakStart->copy()->addMinutes(15);

    $attendanceLog = AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'date' => $breakStart->toDateString(),
        'check_in_time' => $breakStart->copy()->subHours(2),
        'check_out_time' => $breakEnd->copy()->addHours(5),
        'break_start_time' => $breakStart->format('Y-m-d H:i:s'),
        'break_end_time' => $breakEnd->format('Y-m-d H:i:s'),
        'total_break_minutes' => -405,
    ]);
    $validAttendanceLog = AttendanceLog::factory()->create([
        'employee_id' => Employee::factory()->create()->id,
        'date' => $breakStart->copy()->addDay()->toDateString(),
        'break_start_time' => $breakStart->copy()->addDay()->format('Y-m-d H:i:s'),
        'break_end_time' => $breakEnd->copy()->addDay()->addMinutes(15)->format('Y-m-d H:i:s'),
        'total_break_minutes' => 30,
    ]);

    expect(is_file(base_path(ATTENDANCE_BREAK_DURATION_BACKFILL)))->toBeTrue();

    $this->artisan('migrate:rollback', [
        '--path' => ATTENDANCE_BREAK_DURATION_BACKFILL,
    ])->assertOk();
    $this->artisan('migrate', [
        '--path' => ATTENDANCE_BREAK_DURATION_BACKFILL,
    ])->assertOk();

    expect($attendanceLog->fresh()->total_break_minutes)->toBe(15)
        ->and($validAttendanceLog->fresh()->total_break_minutes)->toBe(30);

    $this->artisan('migrate:rollback', [
        '--path' => ATTENDANCE_BREAK_DURATION_BACKFILL,
    ])->assertOk();
    $this->artisan('migrate', [
        '--path' => ATTENDANCE_BREAK_DURATION_BACKFILL,
    ])->assertOk();

    expect($attendanceLog->fresh()->total_break_minutes)->toBe(15)
        ->and($validAttendanceLog->fresh()->total_break_minutes)->toBe(30);
});
