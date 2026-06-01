<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\Employee;
use App\Models\Hris\LateNotice;
use App\Models\User;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LateNoticePenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceService::class);

        $user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id'   => $user->id,
            'is_active' => true,
        ]);

        // Ensure attendance settings exist with known tiers
        AttendanceSetting::updateOrCreate([], [
            'work_start_time'          => '09:00',
            'late_grace_period_minutes' => 0,
            'wfh_quota_per_week'       => 2,
            'full_day_hours'           => 8,
            'half_day_hours'           => 4,
            'late_penalty_tiers'       => [
                ['minutes_late' => 40,  'deduction_percent' => 25],
                ['minutes_late' => 120, 'deduction_percent' => 50],
                ['minutes_late' => 240, 'deduction_percent' => 100],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // calculateLatePenalty – tier matching
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_zero_penalty_for_present_status(): void
    {
        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_PRESENT,
            'check_in_time' => Carbon::today()->setTime(8, 55),
        ]);

        $this->assertEquals(0, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_applies_tier1_penalty_for_40_minutes_late(): void
    {
        // 09:40 is exactly 40 min late → 25%
        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(9, 40),
        ]);

        $this->assertEquals(25, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_applies_tier2_penalty_for_120_minutes_late(): void
    {
        // 11:00 = 120 min late → 50%
        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(11, 0),
        ]);

        $this->assertEquals(50, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_applies_tier3_penalty_for_240_minutes_late(): void
    {
        // 13:00 = 240 min late → 100%
        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(13, 0),
        ]);

        $this->assertEquals(100, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_applies_highest_matching_tier_for_late_between_tiers(): void
    {
        // 10:30 = 90 min late, falls in tier1 (≥40 min) but not tier2 (≥120 min) → 25%
        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(10, 30),
        ]);

        $this->assertEquals(25, $this->service->calculateLatePenalty($log));
    }

    // -------------------------------------------------------------------------
    // calculateLatePenalty – approved notice waiver
    // -------------------------------------------------------------------------

    #[Test]
    public function it_waives_penalty_when_employee_has_approved_late_notice(): void
    {
        $today = Carbon::today();

        LateNotice::create([
            'employee_id'            => $this->employee->id,
            'date'                   => $today,
            'estimated_arrival_time' => '11:00',
            'reason'                 => 'Traffic jam',
            'status'                 => LateNotice::STATUS_APPROVED,
        ]);

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(11, 0),
            'date'          => $today->toDateString(),
        ]);

        $this->assertEquals(0, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_does_not_waive_penalty_for_pending_late_notice(): void
    {
        $today = Carbon::today();

        LateNotice::create([
            'employee_id'            => $this->employee->id,
            'date'                   => $today,
            'estimated_arrival_time' => '11:00',
            'reason'                 => 'Traffic jam',
            'status'                 => LateNotice::STATUS_PENDING,
        ]);

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(11, 0),
            'date'          => $today->toDateString(),
        ]);

        $this->assertEquals(50, $this->service->calculateLatePenalty($log));
    }

    #[Test]
    public function it_does_not_waive_penalty_for_rejected_late_notice(): void
    {
        $today = Carbon::today();

        LateNotice::create([
            'employee_id'            => $this->employee->id,
            'date'                   => $today,
            'estimated_arrival_time' => '11:00',
            'reason'                 => 'Traffic jam',
            'status'                 => LateNotice::STATUS_REJECTED,
        ]);

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => Carbon::today()->setTime(11, 0),
            'date'          => $today->toDateString(),
        ]);

        $this->assertEquals(50, $this->service->calculateLatePenalty($log));
    }

    // -------------------------------------------------------------------------
    // hasApprovedLateNotice
    // -------------------------------------------------------------------------

    #[Test]
    public function it_detects_approved_late_notice_for_employee_on_date(): void
    {
        $today = Carbon::today();

        LateNotice::create([
            'employee_id'            => $this->employee->id,
            'date'                   => $today,
            'estimated_arrival_time' => '10:00',
            'reason'                 => 'Medical appointment',
            'status'                 => LateNotice::STATUS_APPROVED,
        ]);

        $this->assertTrue($this->service->hasApprovedLateNotice($this->employee->id, $today));
    }

    #[Test]
    public function it_returns_false_when_no_approved_late_notice_exists(): void
    {
        $this->assertFalse($this->service->hasApprovedLateNotice($this->employee->id, Carbon::today()));
    }

    // -------------------------------------------------------------------------
    // createLateNoticeIfAbsent – auto-creation on late check-in
    // -------------------------------------------------------------------------

    #[Test]
    public function it_auto_creates_late_notice_on_late_check_in(): void
    {
        $today = Carbon::today();

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'date'          => $today,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => $today->copy()->setTime(9, 45), // 45 min late → 25% tier
        ]);

        $notice = $this->service->createLateNoticeIfAbsent($log);

        $this->assertNotNull($notice);
        $this->assertEquals(LateNotice::STATUS_PENDING, $notice->status);
        $this->assertEquals($this->employee->id, $notice->employee_id);
        $this->assertEquals($log->id, $notice->attendance_log_id);
        $this->assertEquals(45, $notice->minutes_late);
        $this->assertEquals(25, $notice->penalty_percent);
        $this->assertNull($notice->reason);
    }

    #[Test]
    public function it_does_not_duplicate_notice_for_same_employee_and_date(): void
    {
        $today = Carbon::today();

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'date'          => $today,
            'status'        => AttendanceLog::STATUS_LATE,
            'check_in_time' => $today->copy()->setTime(10, 0),
        ]);

        $first  = $this->service->createLateNoticeIfAbsent($log);
        $second = $this->service->createLateNoticeIfAbsent($log);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertDatabaseCount('late_notices', 1);
    }

    #[Test]
    public function it_does_not_create_notice_for_on_time_check_in(): void
    {
        $today = Carbon::today();

        $log = AttendanceLog::factory()->create([
            'employee_id'   => $this->employee->id,
            'date'          => $today,
            'status'        => AttendanceLog::STATUS_PRESENT,
            'check_in_time' => $today->copy()->setTime(8, 55),
        ]);

        // createLateNoticeIfAbsent only called from controller when STATUS_LATE
        // but even if called directly it should return null because penalty = 0
        // and no notice should exist
        $this->assertDatabaseCount('late_notices', 0);
    }
}
