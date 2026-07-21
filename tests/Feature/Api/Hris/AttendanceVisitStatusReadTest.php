<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 14:00:00', 'Asia/Jakarta'));

    $this->employee = Employee::factory()->create();
    Sanctum::actingAs($this->employee->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('Daily endpoint', function () {
    it('returns a stored visit status unchanged instead of computing absent', function () {
        // Mirrors the ClientVisitObserver marker: status=visit, no check-in yet.
        AttendanceLog::factory()->absent()->create([
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'status' => AttendanceLog::STATUS_VISIT,
            'source' => AttendanceLog::SOURCE_VISIT,
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data.status', AttendanceLog::STATUS_VISIT);
    });

    it('returns a stored holiday status unchanged', function () {
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'check_in_time' => '2026-07-20 08:00:00',
            'status' => AttendanceLog::STATUS_HOLIDAY,
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data.status', AttendanceLog::STATUS_HOLIDAY);
    });

    it('still recomputes a normal status rather than freezing it', function () {
        // Stored status is stale ('absent') but the employee actually checked
        // in on time; the guard must not suppress the recompute for statuses
        // outside getNonComputableStatuses().
        AttendanceLog::factory()->checkedInOnly()->create([
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'check_in_time' => '2026-07-20 08:00:00',
            'status' => AttendanceLog::STATUS_ABSENT,
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data.status', AttendanceLog::STATUS_PRESENT);
    });

    it('still computes late for a genuinely late check-in', function () {
        AttendanceLog::factory()->checkedInOnly()->create([
            'employee_id' => $this->employee->id,
            'date' => '2026-07-20',
            'check_in_time' => '2026-07-20 10:00:00',
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $response = getJson('/api/hris/attendance/daily');

        $response->assertOk()
            ->assertJsonPath('data.status', AttendanceLog::STATUS_LATE);
    });
});

describe('History endpoint', function () {
    it('returns stored visit and holiday statuses unchanged alongside computed ones', function () {
        // 'date' is stored via a custom_datetime cast, so pass Carbon
        // instances (not raw strings) to keep the stored format consistent
        // with the Carbon bounds forDateRange() queries against.
        AttendanceLog::factory()->absent()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::parse('2026-07-18'),
            'status' => AttendanceLog::STATUS_VISIT,
            'source' => AttendanceLog::SOURCE_VISIT,
        ]);

        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::parse('2026-07-19'),
            'check_in_time' => '2026-07-19 08:00:00',
            'status' => AttendanceLog::STATUS_HOLIDAY,
        ]);

        AttendanceLog::factory()->checkedInOnly()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::parse('2026-07-20'),
            'check_in_time' => '2026-07-20 08:00:00',
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $response = getJson('/api/hris/attendance/history?from=2026-07-18&to=2026-07-20');

        $response->assertOk()->assertJsonCount(3, 'data');

        $byDate = collect($response->json('data'))->keyBy('date');
        expect($byDate['2026-07-18']['status'])->toBe(AttendanceLog::STATUS_VISIT)
            ->and($byDate['2026-07-19']['status'])->toBe(AttendanceLog::STATUS_HOLIDAY)
            ->and($byDate['2026-07-20']['status'])->toBe(AttendanceLog::STATUS_PRESENT);
    });
});
