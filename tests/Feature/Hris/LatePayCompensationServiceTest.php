<?php

namespace Tests\Feature\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LatePayCompensationBatch;
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

        $batch = $this->latePayCompensationService->generateBatch($scheduled, $actual, null, 1.0);

        $this->assertEquals(LatePayCompensationBatch::STATUS_CALCULATED, $batch->status);
        $this->assertSame(2, $batch->days_late);

        $item = $batch->items()->where('employee_id', $this->employee->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(10_000_000.0, (float) $item->basic_salary_snapshot);
        $this->assertSame(200_000.0, (float) $item->total_amount);
    }

    #[Test]
    public function it_throws_when_duplicate_scheduled_and_actual_dates(): void
    {
        $scheduled = Carbon::parse('2026-05-01');
        $actual = Carbon::parse('2026-05-03');

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

        $batch = $this->latePayCompensationService->generateBatch(
            Carbon::parse('2026-06-01'),
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
}
