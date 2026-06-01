<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\OvertimePayment;
use App\Models\User;
use App\Services\Hris\OvertimePayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OvertimePayServiceTest extends TestCase
{
    use RefreshDatabase;

    public $service;
    public $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OvertimePayService::class);

        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id'   => $this->user->id,
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // getPreviousWeekDates
    // -------------------------------------------------------------------------

    #[Test]
    public function it_calculates_previous_week_dates(): void
    {
        $from = Carbon::parse('2026-04-08'); // Tuesday

        $dates = OvertimePayService::getPreviousWeekDates($from);

        $this->assertEquals('2026-03-30', $dates['start']->toDateString()); // Monday
        $this->assertEquals('2026-04-05', $dates['end']->toDateString());   // Sunday
        $this->assertEquals('2026-04-07', $dates['payment']->toDateString()); // Tuesday
    }

    // -------------------------------------------------------------------------
    // generateBatch
    // -------------------------------------------------------------------------

    #[Test]
    public function it_generates_batch_from_approved_unpaid_logs(): void
    {
        $manager = User::factory()->create();

        $weekStart = Carbon::parse('2026-03-30');
        $weekEnd   = Carbon::parse('2026-04-05');

        // Create 2 approved unpaid logs for the week
        OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Task A',
            'start_time'       => Carbon::parse('2026-04-01 18:00'),
            'end_time'         => Carbon::parse('2026-04-01 20:00'),
            'duration_minutes' => 120,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_APPROVED,
        ]);

        OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Task B',
            'start_time'       => Carbon::parse('2026-04-02 18:00'),
            'end_time'         => Carbon::parse('2026-04-02 19:00'),
            'duration_minutes' => 60,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_APPROVED,
        ]);

        $batch = $this->service->generateBatch(
            $weekStart,
            $weekEnd,
            Carbon::parse('2026-04-07')
        );

        $this->assertEquals(OvertimePayBatch::STATUS_CALCULATED, $batch->status);
        $this->assertEquals(1, $batch->total_employees);

        $payment = $batch->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->employee->id, $payment->employee_id);
        $this->assertEquals(2, $payment->total_sessions);
        $this->assertEquals(180, $payment->total_duration_minutes);
        $this->assertGreaterThan(0, $payment->total_amount);
    }

    #[Test]
    public function it_skips_pending_and_running_logs(): void
    {
        $manager   = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $weekEnd   = Carbon::parse('2026-04-05');

        OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Pending task',
            'start_time'       => Carbon::parse('2026-04-01 18:00'),
            'end_time'         => Carbon::parse('2026-04-01 19:00'),
            'duration_minutes' => 60,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_PENDING,
        ]);

        $batch = $this->service->generateBatch(
            $weekStart,
            $weekEnd,
            Carbon::parse('2026-04-07')
        );

        $this->assertEquals(0, $batch->total_employees);
        $this->assertEquals(0, $batch->total_amount);
    }

    #[Test]
    public function it_skips_logs_outside_the_week(): void
    {
        $manager   = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $weekEnd   = Carbon::parse('2026-04-05');

        // Log is one week earlier
        OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Last week task',
            'start_time'       => Carbon::parse('2026-03-23 18:00'),
            'end_time'         => Carbon::parse('2026-03-23 19:00'),
            'duration_minutes' => 60,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_APPROVED,
        ]);

        $batch = $this->service->generateBatch(
            $weekStart,
            $weekEnd,
            Carbon::parse('2026-04-07')
        );

        $this->assertEquals(0, $batch->total_employees);
    }

    #[Test]
    public function it_throws_when_batch_already_exists_for_week(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/already exists/');

        $weekStart = Carbon::parse('2026-03-30');
        $weekEnd   = Carbon::parse('2026-04-05');
        $payment   = Carbon::parse('2026-04-07');

        // Generate once
        $this->service->generateBatch($weekStart, $weekEnd, $payment);

        // Generate again — should throw
        $this->service->generateBatch($weekStart, $weekEnd, $payment);
    }

    #[Test]
    public function approved_logs_are_linked_to_payment_after_batch_generation(): void
    {
        $manager   = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $weekEnd   = Carbon::parse('2026-04-05');

        $log = OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Link test',
            'start_time'       => Carbon::parse('2026-04-01 18:00'),
            'end_time'         => Carbon::parse('2026-04-01 19:00'),
            'duration_minutes' => 60,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_APPROVED,
        ]);

        $this->service->generateBatch($weekStart, $weekEnd, Carbon::parse('2026-04-07'));

        $log->refresh();
        $this->assertNotNull($log->overtime_payment_id);
    }

    #[Test]
    public function log_linked_to_existing_payment_is_excluded_from_new_batches(): void
    {
        $manager    = User::factory()->create();
        $week1Start = Carbon::parse('2026-03-30');
        $week1End   = Carbon::parse('2026-04-05');

        $log = OvertimeLog::create([
            'user_id'          => $this->user->id,
            'manager_id'       => $manager->id,
            'task_name'        => 'Already paid',
            'start_time'       => Carbon::parse('2026-04-01 18:00'),
            'end_time'         => Carbon::parse('2026-04-01 19:00'),
            'duration_minutes' => 60,
            'hourly_rate'      => 60_000,
            'status'           => OvertimeLog::STATUS_APPROVED,
        ]);

        // Generate week1 batch — log gets overtime_payment_id set
        $this->service->generateBatch($week1Start, $week1End, Carbon::parse('2026-04-07'));
        $log->refresh();
        $this->assertNotNull($log->overtime_payment_id, 'Log should be linked after batch generation');

        // Verify scopeUnpaid() no longer includes this log
        $unpaidCount = OvertimeLog::approved()->unpaid()->count();
        $this->assertEquals(0, $unpaidCount, 'Linked log must not appear in unpaid scope');

        // A batch for week2 should find 0 employees (log is already linked)
        $week2Start = Carbon::parse('2026-04-07');
        $week2End   = Carbon::parse('2026-04-13');
        $week2Batch = $this->service->generateBatch($week2Start, $week2End, Carbon::parse('2026-04-15'));

        $this->assertEquals(0, $week2Batch->total_employees);
    }

    // -------------------------------------------------------------------------
    // getMonthlyPaidTotal
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_zero_when_no_paid_batches(): void
    {
        $total = $this->service->getMonthlyPaidTotal(
            $this->employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEquals(0.0, $total);
    }

    #[Test]
    public function it_sums_paid_payments_within_period(): void
    {
        // Create a paid batch within April
        $batch = OvertimePayBatch::create([
            'week_start_date' => '2026-04-01',
            'week_end_date'   => '2026-04-06',
            'payment_date'    => '2026-04-08',
            'status'          => OvertimePayBatch::STATUS_PAID,
            'total_employees' => 1,
            'total_amount'    => 150000,
        ]);

        OvertimePayment::create([
            'overtime_pay_batch_id'  => $batch->id,
            'employee_id'            => $this->employee->id,
            'total_sessions'         => 1,
            'total_duration_minutes' => 60,
            'total_amount'           => 150000,
            'status'                 => OvertimePayment::STATUS_PAID,
        ]);

        $total = $this->service->getMonthlyPaidTotal(
            $this->employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEquals(150000.0, $total);
    }

    #[Test]
    public function it_excludes_paid_payments_outside_period(): void
    {
        // March batch — outside April period
        $batch = OvertimePayBatch::create([
            'week_start_date' => '2026-03-24',
            'week_end_date'   => '2026-03-30',
            'payment_date'    => '2026-04-01',
            'status'          => OvertimePayBatch::STATUS_PAID,
            'total_employees' => 1,
            'total_amount'    => 100000,
        ]);

        OvertimePayment::create([
            'overtime_pay_batch_id'  => $batch->id,
            'employee_id'            => $this->employee->id,
            'total_sessions'         => 1,
            'total_duration_minutes' => 60,
            'total_amount'           => 100000,
            'status'                 => OvertimePayment::STATUS_PAID,
        ]);

        $total = $this->service->getMonthlyPaidTotal(
            $this->employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEquals(0.0, $total);
    }

    #[Test]
    public function it_excludes_non_paid_status_payments(): void
    {
        $batch = OvertimePayBatch::create([
            'week_start_date' => '2026-04-01',
            'week_end_date'   => '2026-04-06',
            'payment_date'    => '2026-04-08',
            'status'          => OvertimePayBatch::STATUS_CALCULATED,
            'total_employees' => 1,
            'total_amount'    => 100000,
        ]);

        OvertimePayment::create([
            'overtime_pay_batch_id'  => $batch->id,
            'employee_id'            => $this->employee->id,
            'total_sessions'         => 1,
            'total_duration_minutes' => 60,
            'total_amount'           => 100000,
            'status'                 => OvertimePayment::STATUS_PENDING, // not paid
        ]);

        $total = $this->service->getMonthlyPaidTotal(
            $this->employee,
            Carbon::parse('2026-04-01'),
            Carbon::parse('2026-04-30')
        );

        $this->assertEquals(0.0, $total);
    }
}
