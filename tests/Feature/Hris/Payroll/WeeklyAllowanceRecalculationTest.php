<?php

use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages\ListWeeklyAllowanceBatches;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\WeeklyAllowanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const R_WEEK_START = '2025-10-06'; // Monday
const R_WEEK_END = '2025-10-10';   // Friday
const R_PAY_DATE = '2025-10-14';   // Tuesday

function recalcEmployee(array $overrides = []): Employee
{
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    SalaryStructure::create(array_merge([
        'employee_id' => $employee->id,
        'effective_date' => '2025-01-01',
        'basic_salary' => 4_000_000,
        'daily_allowance_rate' => 70_000,
        'weekly_allowance_enabled' => true,
        'pph21_method' => 'gross',
        'jkk_risk_level' => 'low',
    ], $overrides));

    return $employee;
}

function recalcPresent(Employee $employee, string $date): void
{
    AttendanceLog::create([
        'employee_id' => $employee->id,
        'date' => Carbon::parse($date),
        'status' => AttendanceLog::STATUS_PRESENT,
        'mode' => AttendanceLog::MODE_OFFICE,
        'source' => AttendanceLog::SOURCE_WEB,
    ]);
}

function recalcGenerate(): WeeklyAllowanceBatch
{
    return app(WeeklyAllowanceService::class)->generateBatch(
        Carbon::parse(R_WEEK_START),
        Carbon::parse(R_WEEK_END),
        Carbon::parse(R_PAY_DATE),
    );
}

beforeEach(function () {
    $this->service = app(WeeklyAllowanceService::class);
});

describe('WeeklyAllowanceService::recalculateBatch', function () {

    it('recomputes payment totals after attendance is added', function () {
        $employee = recalcEmployee(['daily_allowance_rate' => 70_000]);
        recalcPresent($employee, '2025-10-06'); // Mon
        recalcPresent($employee, '2025-10-07'); // Tue
        recalcPresent($employee, '2025-10-08'); // Wed

        $batch = recalcGenerate();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();
        expect((float) $payment->total_amount)->toBe(210_000.0)
            ->and($payment->days_present)->toBe(3);

        // HR later records two more attendance days, then recalculates.
        recalcPresent($employee, '2025-10-09'); // Thu
        recalcPresent($employee, '2025-10-10'); // Fri

        $this->service->recalculateBatch($batch);

        $payment->refresh();
        expect((float) $payment->total_amount)->toBe(350_000.0)
            ->and($payment->days_present)->toBe(5);
    });

    it('applies a mid-week salary structure change per day on recalculation', function () {
        $employee = recalcEmployee(['daily_allowance_rate' => 50_000]);
        foreach (['2025-10-06', '2025-10-07', '2025-10-08', '2025-10-09', '2025-10-10'] as $d) {
            recalcPresent($employee, $d);
        }

        $batch = recalcGenerate();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();
        expect((float) $payment->total_amount)->toBe(250_000.0); // 5 x 50k

        // Rate raised to 100k effective Wednesday 2025-10-08.
        SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => '2025-10-08',
            'basic_salary' => 4_000_000,
            'daily_allowance_rate' => 100_000,
            'weekly_allowance_enabled' => true,
            'pph21_method' => 'gross',
            'jkk_risk_level' => 'low',
        ]);

        $this->service->recalculateBatch($batch);

        // Mon,Tue @ 50k + Wed,Thu,Fri @ 100k = 100k + 300k = 400k.
        $payment->refresh();
        expect((float) $payment->total_amount)->toBe(400_000.0);
    });

    it('is idempotent — recalculating twice yields the same totals', function () {
        $employee = recalcEmployee(['daily_allowance_rate' => 70_000]);
        foreach (['2025-10-06', '2025-10-07', '2025-10-08'] as $d) {
            recalcPresent($employee, $d);
        }

        $batch = recalcGenerate();

        $this->service->recalculateBatch($batch);
        $first = (float) $batch->payments()->where('employee_id', $employee->id)->first()->total_amount;

        $this->service->recalculateBatch($batch);
        $second = (float) $batch->payments()->where('employee_id', $employee->id)->first()->total_amount;

        expect($first)->toBe(210_000.0)->and($second)->toBe($first);
    });

    it('recalculates the batch summary once regardless of payment count (no per-payment N+1)', function () {
        $employees = collect(range(1, 3))->map(fn () => recalcEmployee(['daily_allowance_rate' => 70_000]));
        foreach ($employees as $employee) {
            recalcPresent($employee, '2025-10-06');
        }

        $batch = recalcGenerate();

        foreach ($employees as $employee) {
            recalcPresent($employee, '2025-10-07');
        }

        DB::enableQueryLog();
        $this->service->recalculateBatch($batch);
        $summaryQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'sum(') && str_contains($q['query'], 'weekly_allowance_payments'))
            ->count();
        DB::disableQueryLog();

        expect($summaryQueries)->toBe(1);
    });

    it('rejects recalculation of a batch in finance approval', function () {
        $employee = recalcEmployee();
        recalcPresent($employee, '2025-10-06');
        $batch = recalcGenerate();
        $batch->update(['status' => WeeklyAllowanceBatch::STATUS_PENDING_FINANCE]);

        expect(fn () => $this->service->recalculateBatch($batch))
            ->toThrow(\RuntimeException::class);
    });

    it('rejects recalculation of a paid batch and preserves the paid values (immutability)', function () {
        $employee = recalcEmployee(['daily_allowance_rate' => 70_000]);
        recalcPresent($employee, '2025-10-06');
        recalcPresent($employee, '2025-10-07');

        $batch = recalcGenerate();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();
        $paidTotal = (float) $payment->total_amount; // 140k
        $batch->update(['status' => WeeklyAllowanceBatch::STATUS_PAID]);

        // Attendance changes after payment — must NOT rewrite the paid record.
        recalcPresent($employee, '2025-10-08');
        recalcPresent($employee, '2025-10-09');

        expect(fn () => $this->service->recalculateBatch($batch))
            ->toThrow(\RuntimeException::class);

        $payment->refresh();
        expect((float) $payment->total_amount)->toBe($paidTotal)
            ->and($payment->days_present)->toBe(2);
    });
});

describe('WeeklyAllowanceService::recalculatePayment (single)', function () {
    it('rejects recalculating a payment whose batch is locked', function () {
        $employee = recalcEmployee();
        recalcPresent($employee, '2025-10-06');
        $batch = recalcGenerate();
        $payment = $batch->payments()->where('employee_id', $employee->id)->first();
        $batch->update(['status' => WeeklyAllowanceBatch::STATUS_PAID]);

        expect(fn () => $this->service->recalculatePayment($payment->fresh()))
            ->toThrow(\RuntimeException::class);
    });
});

describe('Weekly allowance recalculate HR action', function () {
    it('lets an authorized user recalculate a non-locked batch from the table action', function () {
        $admin = User::factory()->create();
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']));
        }

        $employee = recalcEmployee(['daily_allowance_rate' => 70_000]);
        recalcPresent($employee, '2025-10-06');
        $batch = recalcGenerate();

        // More attendance recorded before HR recalculates.
        recalcPresent($employee, '2025-10-07');

        Livewire::actingAs($admin)
            ->test(ListWeeklyAllowanceBatches::class)
            ->callTableAction('recalculate', $batch);

        $payment = $batch->payments()->where('employee_id', $employee->id)->first();
        expect((float) $payment->total_amount)->toBe(140_000.0)
            ->and($payment->days_present)->toBe(2);
    });

    it('hides the recalculate action for a locked batch', function () {
        $admin = User::factory()->create();
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']));
        }

        $employee = recalcEmployee();
        recalcPresent($employee, '2025-10-06');
        $batch = recalcGenerate();
        $batch->update(['status' => WeeklyAllowanceBatch::STATUS_PAID]);

        Livewire::actingAs($admin)
            ->test(ListWeeklyAllowanceBatches::class)
            ->assertTableActionHidden('recalculate', $batch);
    });
});
