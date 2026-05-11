<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\LatePayCompensationPartialPayment;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\LatePayCompensationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LatePayCompensationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LatePayCompensationService $latePayCompensationService;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->latePayCompensationService = app(LatePayCompensationService::class);

        $employeeUser = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'is_active' => true,
            'bank_name' => 'BCA',
            'bank_account_number' => '123',
            'bank_account_holder' => 'Test User',
        ]);

        SalaryStructure::factory()
            ->for($this->employee)
            ->withBasicSalary(10_000_000)
            ->create([
                'effective_date' => '2026-01-01',
            ]);
    }

    /**
     * Create a PayrunBatch + PayrollRecord so generateBatch() can read THP for $this->employee.
     */
    protected function createPayrollFor(Carbon $scheduledPayDate, float $netIncome = 8_000_000.0): PayrollRecord
    {
        $batch = PayrunBatch::factory()->create([
            'pay_date' => $scheduledPayDate->toDateString(),
            'period_start_date' => $scheduledPayDate->copy()->startOfMonth()->toDateString(),
            'period_end_date' => $scheduledPayDate->copy()->endOfMonth()->toDateString(),
        ]);

        return PayrollRecord::factory()
            ->forBatch($batch)
            ->forEmployee($this->employee)
            ->create([
                'net_income' => $netIncome,
            ]);
    }

    #[Test]
    public function it_calculates_calendar_days_late(): void
    {
        $scheduled = Carbon::parse('2026-04-25');
        $actual = Carbon::parse('2026-04-26');

        $this->assertSame(1, LatePayCompensationService::calculateDaysLate($scheduled, $actual));

        $this->assertSame(0, LatePayCompensationService::calculateDaysLate($scheduled, $scheduled));
        $this->assertSame(0, LatePayCompensationService::calculateDaysLate($actual, $scheduled));
    }

    #[Test]
    public function it_generates_batch_with_correct_compensation_amount(): void
    {
        $scheduled = Carbon::parse('2026-04-25');
        $actual = Carbon::parse('2026-04-27');

        $this->createPayrollFor($scheduled, 8_000_000);

        $batch = $this->latePayCompensationService->generateBatch($scheduled, $actual, null, 1.0);

        $this->assertEquals(LatePayCompensationBatch::STATUS_CALCULATED, $batch->status);
        $this->assertSame(2, $batch->days_late);

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(10_000_000.0, (float) $item->basic_salary_snapshot);
        $this->assertEquals(8_000_000.0, (float) $item->thp_snapshot);
        // 8M (THP) * 1% * 2 days = 160,000
        $this->assertSame(160_000.0, (float) $item->total_amount);
    }

    #[Test]
    public function it_throws_when_duplicate_scheduled_and_actual_dates(): void
    {
        $scheduled = Carbon::parse('2026-05-01');
        $actual = Carbon::parse('2026-05-03');

        $this->createPayrollFor($scheduled);

        $this->latePayCompensationService->generateBatch($scheduled, $actual);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('already exists');

        $this->latePayCompensationService->generateBatch($scheduled, $actual);
    }

    #[Test]
    public function it_throws_when_no_days_late(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No compensation due');

        $this->latePayCompensationService->generateBatch(
            Carbon::parse('2026-05-10'),
            Carbon::parse('2026-05-10')
        );
    }

    #[Test]
    public function it_skips_employees_without_salary_structure(): void
    {
        $user2 = User::factory()->create();
        Employee::factory()->create([
            'user_id' => $user2->id,
            'is_active' => true,
        ]);

        $scheduled = Carbon::parse('2026-06-01');
        $this->createPayrollFor($scheduled);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-06-05')
        );

        $this->assertSame(1, $batch->total_employees);
        $this->assertSame(1, $batch->items()->count());
    }

    #[Test]
    public function it_throws_when_no_eligible_employees(): void
    {
        SalaryStructure::query()->delete();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No employees with an active salary structure');

        $this->latePayCompensationService->generateBatch(
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-03')
        );
    }

    // --- Partial Payment / Multi-Phase Tests ---

    #[Test]
    public function compute_item_amount_without_partial_payments(): void
    {
        $amount = LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-04-27'),
            [],
            8_000_000
        );

        // 8M (THP) * 1% * 2 days = 160,000
        $this->assertSame(160_000.0, $amount);
    }

    #[Test]
    public function compute_item_amount_with_single_partial_payment(): void
    {
        // THP=8M, 1%/day, scheduled Apr 25, actual Apr 30 (5 days late)
        // Partial: 50% paid on Apr 27
        // Phase 1: Apr 25–27 (2 days) → 1% × 8M × 2 = 160,000
        // Phase 2: Apr 27–30 (3 days) → 1% × (8M × 50%) × 3 = 1% × 4M × 3 = 120,000
        // Total: 280,000
        $partials = collect([
            (object) ['payment_date' => '2026-04-27', 'paid_percent' => 50.0],
        ]);

        $amount = LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-04-30'),
            $partials,
            8_000_000
        );

        $this->assertSame(280_000.0, $amount);
    }

    #[Test]
    public function compute_item_amount_with_multiple_partial_payments(): void
    {
        // THP=8M, 1%/day, scheduled Apr 25, actual May 2 (7 days)
        // Partial 1: 30% paid on Apr 28 (day 3)
        // Partial 2: 20% paid on Apr 30 (day 5)
        // Phase 1: Apr 25–28 (3 days) → 1% × 8M × 3 = 240,000
        // Phase 2: Apr 28–30 (2 days) → 1% × (8M × 70%) × 2 = 1% × 5.6M × 2 = 112,000
        // Phase 3: Apr 30–May 2 (2 days) → 1% × (8M × 50%) × 2 = 1% × 4M × 2 = 80,000
        // Total: 432,000
        $partials = collect([
            (object) ['payment_date' => '2026-04-28', 'paid_percent' => 30.0],
            (object) ['payment_date' => '2026-04-30', 'paid_percent' => 20.0],
        ]);

        $amount = LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-05-02'),
            $partials,
            8_000_000
        );

        $this->assertSame(432_000.0, $amount);
    }

    #[Test]
    public function compute_item_amount_with_three_partial_payments(): void
    {
        // THP=8M, 1%/day, scheduled Apr 25, actual May 5 (10 days)
        // Partial 1: 20% on Apr 27 (day 2)
        // Partial 2: 30% on Apr 30 (day 5)
        // Partial 3: 30% on May 3 (day 8)
        // Phase 1: Apr 25–27 (2 days) → 1% × 8M × 2 = 160,000
        // Phase 2: Apr 27–30 (3 days) → 1% × (8M × 80%) × 3 = 1% × 6.4M × 3 = 192,000
        // Phase 3: Apr 30–May 3 (3 days) → 1% × (8M × 50%) × 3 = 1% × 4M × 3 = 120,000
        // Phase 4: May 3–May 5 (2 days) → 1% × (8M × 20%) × 2 = 1% × 1.6M × 2 = 32,000
        // Total: 504,000
        $partials = collect([
            (object) ['payment_date' => '2026-04-27', 'paid_percent' => 20.0],
            (object) ['payment_date' => '2026-04-30', 'paid_percent' => 30.0],
            (object) ['payment_date' => '2026-05-03', 'paid_percent' => 30.0],
        ]);

        $amount = LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-05-05'),
            $partials,
            8_000_000
        );

        $this->assertSame(504_000.0, $amount);
    }

    #[Test]
    public function all_phases_use_thp_with_partial_reductions(): void
    {
        // THP=6M, 1%/day, Apr 25 → May 1 (6 days), partial 40% on Apr 27
        // Phase 1: Apr 25–27 (2 days) → 1% × 6M × 2 = 120,000
        // Phase 2: Apr 27–May 1 (4 days) → 1% × (6M × 60%) × 4 = 1% × 3.6M × 4 = 144,000
        // Total: 264,000
        $partials = collect([
            (object) ['payment_date' => '2026-04-27', 'paid_percent' => 40.0],
        ]);

        $amount = LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-05-01'),
            $partials,
            6_000_000
        );

        $this->assertSame(264_000.0, $amount);
    }

    #[Test]
    public function throws_when_thp_snapshot_missing(): void
    {
        $partials = collect([
            (object) ['payment_date' => '2026-04-27', 'paid_percent' => 50.0],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Take Home Pay');

        LatePayCompensationService::computeItemAmount(
            10_000_000,
            1.0,
            Carbon::parse('2026-04-25'),
            Carbon::parse('2026-04-30'),
            $partials,
            null
        );
    }

    #[Test]
    public function recalculate_item_updates_total_amount_with_partial_payments(): void
    {
        $scheduled = Carbon::parse('2026-08-25');
        $this->createPayrollFor($scheduled, 8_000_000);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-08-30'),
            null,
            1.0
        );

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();

        // Before partials: 8M (THP) * 1% * 5 days = 400,000
        $this->assertSame(400_000.0, (float) $item->total_amount);

        // Add 50% partial on day 3
        LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-08-28',
            'paid_percent' => 50.0,
        ]);

        $item = $this->latePayCompensationService->recalculateItem($item);

        // Phase 1: Aug 25–28 (3 days) → 1% × 8M × 3 = 240,000
        // Phase 2: Aug 28–30 (2 days) → 1% × 4M × 2 = 80,000
        // Total: 320,000
        $this->assertSame(320_000.0, (float) $item->total_amount);
    }

    #[Test]
    public function recalculate_batch_updates_all_items_and_totals(): void
    {
        $scheduled = Carbon::parse('2026-09-25');
        $this->createPayrollFor($scheduled, 8_000_000);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-09-30'),
            null,
            1.0
        );

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();

        LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-09-27',
            'paid_percent' => 50.0,
        ]);

        $batch = $this->latePayCompensationService->recalculateBatch($batch);

        $item->refresh();

        // Phase 1: Sep 25–27 (2 days) → 1% × 8M × 2 = 160,000
        // Phase 2: Sep 27–30 (3 days) → 1% × 4M × 3 = 120,000
        // Total: 280,000
        $this->assertSame(280_000.0, (float) $item->total_amount);
        $this->assertEquals(280_000.0, (float) $batch->total_amount);
    }

    #[Test]
    public function partial_payment_total_percent_cannot_exceed_100(): void
    {
        $scheduled = Carbon::parse('2026-10-25');
        $this->createPayrollFor($scheduled);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-10-30'),
            null,
            1.0
        );

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();

        LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-10-27',
            'paid_percent' => 60.0,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('exceeds 100%');

        LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-10-28',
            'paid_percent' => 50.0,
        ]);
    }

    #[Test]
    public function partial_payment_date_must_be_within_batch_range(): void
    {
        $scheduled = Carbon::parse('2026-11-25');
        $this->createPayrollFor($scheduled);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-11-30'),
            null,
            1.0
        );

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment date must be between');

        LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-12-05',
            'paid_percent' => 30.0,
        ]);
    }

    #[Test]
    public function removing_partial_payment_recalculates_to_original_amount(): void
    {
        $scheduled = Carbon::parse('2026-12-25');
        $this->createPayrollFor($scheduled, 8_000_000);

        $batch = $this->latePayCompensationService->generateBatch(
            $scheduled,
            Carbon::parse('2026-12-27'),
            null,
            1.0
        );

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();
        $originalAmount = (float) $item->total_amount;

        // 8M (THP) * 1% * 2 = 160,000
        $this->assertSame(160_000.0, $originalAmount);

        $partial = LatePayCompensationPartialPayment::create([
            'late_pay_compensation_item_id' => $item->id,
            'payment_date' => '2026-12-26',
            'paid_percent' => 50.0,
        ]);

        $item = $this->latePayCompensationService->recalculateItem($item);
        $this->assertNotEquals($originalAmount, (float) $item->total_amount);

        // Remove the partial payment
        $partial->delete();
        $item = $this->latePayCompensationService->recalculateItem($item);

        $this->assertSame($originalAmount, (float) $item->total_amount);
    }
}
