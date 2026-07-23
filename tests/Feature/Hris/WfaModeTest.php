<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\WeeklyAllowanceService;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    // Wednesday, so Monday of the same week can hold a prior remote day
    Carbon::setTestNow(Carbon::parse('2026-07-08 10:00:00', 'Asia/Jakarta'));

    $this->employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);
});

afterEach(fn () => Carbon::setTestNow());

describe('WFA check-in', function () {
    it('accepts a wfa check-in and persists the mode', function () {
        Sanctum::actingAs($this->employee->user);

        postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => 'wfa',
        ])->assertCreated()
            ->assertJsonPath('data.mode', 'wfa');

        expect(AttendanceLog::first()->mode)->toBe(AttendanceLog::MODE_WFA);
    });

    it('counts a wfa day against the shared weekly remote quota', function () {
        Sanctum::actingAs($this->employee->user);

        $monday = Carbon::now('Asia/Jakarta')->startOfWeek(Carbon::MONDAY);
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $monday,
            'mode' => AttendanceLog::MODE_WFA,
            'check_in_time' => $monday->copy()->setTime(9, 0),
        ]);

        getJson('/api/hris/attendance/wfh-quota')
            ->assertOk()
            ->assertJsonPath('data.remaining_quota', 0);

        postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_WFH,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'WFH quota exhausted for this week.');
    });

    it('rejects a wfa check-in when the shared quota is exhausted', function () {
        Sanctum::actingAs($this->employee->user);

        $monday = Carbon::now('Asia/Jakarta')->startOfWeek(Carbon::MONDAY);
        AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $monday,
            'mode' => AttendanceLog::MODE_WFH,
            'check_in_time' => $monday->copy()->setTime(9, 0),
        ]);

        postJson('/api/hris/attendance/check-in', [
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'mode' => AttendanceLog::MODE_WFA,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'WFH quota exhausted for this week.');
    });
});

describe('WFA payroll classification', function () {
    it('counts a wfa day as a remote day in the payroll attendance summary', function () {
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::parse('2026-07-06'),
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_WFA,
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        $summary = app(AttendancePayrollService::class)->getAttendanceSummary(
            $this->employee,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        expect($summary['wfh_days'])->toBe(1)
            ->and($summary['office_days'])->toBe(0);
    });

    it('treats a wfa day like a wfh day in the weekly allowance breakdown', function () {
        $monday = Carbon::parse('2026-07-06');
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'date' => $monday,
            'status' => AttendanceLog::STATUS_HALF_DAY,
            'mode' => AttendanceLog::MODE_WFA,
            'source' => AttendanceLog::SOURCE_MOBILE,
        ]);

        $breakdown = app(WeeklyAllowanceService::class)->getDaysBreakdown(
            $this->employee,
            $monday,
            $monday->copy()->addDays(4),
        );

        expect($breakdown['mon']['present'])->toBeTrue();
    });

    it('pays the full daily allowance for a late wfa day, same as wfh', function () {
        AttendanceSetting::updateOrCreate([], [
            'work_start_time' => '09:00',
            'late_grace_period_minutes' => 0,
            'wfh_quota_per_week' => 2,
            'full_day_hours' => 8,
            'half_day_hours' => 4,
            'late_penalty_tiers' => [
                ['minutes_late' => 40, 'deduction_percent' => 25],
            ],
        ]);

        $batch = PayrunBatch::factory()->create([
            'period_start_date' => Carbon::parse('2026-06-01'),
            'period_end_date' => Carbon::parse('2026-06-30'),
            'status' => 'draft',
        ]);

        SalaryStructure::create([
            'employee_id' => $this->employee->id,
            'effective_date' => Carbon::parse('2026-01-01'),
            'basic_salary' => 4000000,
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        // Monday 2026-06-01, 100 minutes late, WFA mode — remote days skip the late penalty
        AttendanceLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::parse('2026-06-01'),
            'status' => AttendanceLog::STATUS_LATE,
            'mode' => AttendanceLog::MODE_WFA,
            'source' => AttendanceLog::SOURCE_MOBILE,
            'check_in_time' => Carbon::parse('2026-06-01 10:40:00'),
        ]);

        $record = app(PayrollCalculationService::class)->calculateEmployee($this->employee, $batch);

        expect((float) $record->daily_allowance_total)->toBe(70000.00);
    });
});
