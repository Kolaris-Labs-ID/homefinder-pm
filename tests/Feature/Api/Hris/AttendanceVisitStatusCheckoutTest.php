<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
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

it('does not overwrite a visit status when the employee checks out', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
        'status' => AttendanceLog::STATUS_VISIT,
        'source' => AttendanceLog::SOURCE_VISIT,
    ]);

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertOk()
        ->assertJsonPath('data.status', AttendanceLog::STATUS_VISIT);

    $log = AttendanceLog::sole();
    expect($log->status)->toBe(AttendanceLog::STATUS_VISIT)
        ->and($log->check_out_time)->not->toBeNull();
});

it('does not overwrite a holiday status when the employee checks out', function () {
    // status=holiday + check_in_time is how OvertimePayService recognises
    // holiday-work attendance (app/Services/Hris/OvertimePayService.php);
    // computeStatus() can never derive 'holiday' either, so it is exposed
    // to the exact same class of bug as 'visit'.
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
        'status' => AttendanceLog::STATUS_HOLIDAY,
    ]);

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertOk()
        ->assertJsonPath('data.status', AttendanceLog::STATUS_HOLIDAY);

    expect(AttendanceLog::sole()->status)->toBe(AttendanceLog::STATUS_HOLIDAY);
});

it('still computes a normal status on checkout when the log was never a protected status', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
        'status' => AttendanceLog::STATUS_PRESENT,
    ]);

    $response = postJson('/api/hris/attendance/check-out', []);

    // check-in 08:00, check-out 14:00 (server now) = 6h => half_day.
    $response->assertOk()
        ->assertJsonPath('data.status', AttendanceLog::STATUS_HALF_DAY);
});
