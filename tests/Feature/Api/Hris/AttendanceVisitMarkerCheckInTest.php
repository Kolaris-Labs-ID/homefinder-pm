<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
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

it('adopts a sudden client-visit attendance marker instead of colliding on the daily unique index', function () {
    // Scheduling a same-day ("sudden") visit makes ClientVisitObserver
    // create a marker AttendanceLog with status=visit and no check-in time.
    ClientVisit::create([
        'employee_id' => $this->employee->id,
        'client_name' => 'PT Sudden Co',
        'client_address' => 'Jl. Sudirman, Jakarta',
        'purpose' => ClientVisit::PURPOSE_OTHER,
        'visit_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'status' => ClientVisit::STATUS_PLANNED,
    ]);

    $marker = AttendanceLog::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-07-20')
        ->sole();

    expect($marker->status)->toBe(AttendanceLog::STATUS_VISIT)
        ->and($marker->check_in_time)->toBeNull();

    // The employee now performs their own, normal check-in through the
    // regular attendance endpoint — this must not 500 on the pre-existing
    // unique(employee_id, date) row.
    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Check-in recorded successfully.')
        ->assertJsonPath('data.id', $marker->id);

    expect(AttendanceLog::count())->toBe(1);

    $log = AttendanceLog::sole();
    expect($log->check_in_time)->not->toBeNull()
        ->and($log->client_visit_id)->toBe($marker->client_visit_id)
        ->and($log->status)->not->toBe(AttendanceLog::STATUS_VISIT);
});

it('still rejects a genuine duplicate check-in with the duplicate_attendance code', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => '2026-07-20',
        'check_in_time' => '2026-07-20 08:00:00',
    ]);

    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has already checked in for today.')
        ->assertJsonPath('code', 'duplicate_attendance');

    expect(AttendanceLog::count())->toBe(1);
});

it('does not leak database internals when check-in fails unexpectedly', function () {
    $this->mock(AttendanceService::class, function ($mock) {
        $mock->shouldReceive('checkIn')
            ->once()
            ->andThrow(new RuntimeException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry for key attendance_logs.employee_id_date'
            ));
    });

    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => AttendanceLog::MODE_OFFICE,
    ]);

    $response->assertStatus(500);
    expect($response->json('message'))
        ->not->toContain('SQLSTATE')
        ->not->toContain('attendance_logs.employee_id_date');
});

it('does not leak database internals when check-out fails unexpectedly', function () {
    $this->mock(AttendanceService::class, function ($mock) {
        $mock->shouldReceive('checkOut')
            ->once()
            ->andThrow(new RuntimeException(
                'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry for key attendance_logs.employee_id_date'
            ));
    });

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertStatus(500);
    expect($response->json('message'))
        ->not->toContain('SQLSTATE')
        ->not->toContain('attendance_logs.employee_id_date');
});
