<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\SalarySegmentationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function segEmployee(): Employee
{
    return Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);
}

function segStructure(Employee $e, string $date, int $basic, bool $prorate = false): SalaryStructure
{
    return SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => $date, 'basic_salary' => $basic,
        'allowances_fixed' => [], 'allowances_variable' => [], 'deductions' => [],
        'prorate_enabled' => $prorate, 'bpjs_enabled' => false,
        'weekly_allowance_enabled' => false, 'daily_allowance_rate' => 0,
    ]);
}

it('segments a mid-period raise by scheduled working days (reference fixture)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);
    segStructure($e, '2026-07-13', 7500000);

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($result['base_salary'])->toBe('6978260.87');
    expect($result['divisor'])->toBe(23);
    expect($result['segments'])->toHaveCount(2);
    expect($result['segments'][0])->toMatchArray([
        'from' => '2026-07-01', 'to' => '2026-07-12', 'basic_salary' => '6000000.00', 'scheduled_days' => 8, 'payable_days' => 8,
    ]);
    expect($result['segments'][1])->toMatchArray([
        'from' => '2026-07-13', 'to' => '2026-07-31', 'basic_salary' => '7500000.00', 'scheduled_days' => 15, 'payable_days' => 15,
    ]);
});

it('returns exactly one full monthly salary when there is no change (invariant 1)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($result['base_salary'])->toBe('6000000.00');
    expect($result['segments'])->toHaveCount(1);
});

it('applies the new salary to the whole period when effective on the first day (invariant 2)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);
    segStructure($e, '2026-07-01', 7500000);

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($result['base_salary'])->toBe('7500000.00');
});

it('ignores a salary effective after the period (invariant 3)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);
    segStructure($e, '2026-08-01', 7500000);

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    expect($result['base_salary'])->toBe('6000000.00');
});

it('allocates payable days per segment under prorate without double-counting (B2)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000, prorate: true);
    segStructure($e, '2026-07-13', 7500000, prorate: true);

    for ($d = Carbon::parse('2026-07-01'); $d->lte(Carbon::parse('2026-07-31')); $d->addDay()) {
        if ($d->isWeekend()) {
            continue;
        }
        \App\Models\Hris\AttendanceLog::factory()->office()->create([
            'employee_id' => $e->id, 'date' => $d->toDateString(),
            'status' => \App\Models\Hris\AttendanceLog::STATUS_PRESENT,
        ]);
    }

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), prorateEnabled: true
    );

    expect($result['base_salary'])->toBe('6978260.87');
    expect(collect($result['segments'])->sum('payable_days'))->toBe(23);
});

it('splits three mid-period changes into three weighted segments', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);
    segStructure($e, '2026-07-08', 6500000); // Wed
    segStructure($e, '2026-07-20', 7500000); // Mon

    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );

    // weekdays: 1-7 ->5, 8-19 ->8, 20-31 ->10 ; (6M*5 + 6.5M*8 + 7.5M*10)/23 = 157,000,000/23
    expect($result['segments'])->toHaveCount(3);
    expect($result['base_salary'])->toBe('6826086.96');
    expect(collect($result['segments'])->sum('scheduled_days'))->toBe(23);
});

it('throws PayrollCoverageException when a segment has no covering structure (no silent understatement)', function () {
    $e = segEmployee();
    segStructure($e, '2026-07-20', 6000000); // nothing covers 1-19 Jul

    app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')
    );
})->throws(\App\Exceptions\Hris\PayrollCoverageException::class);

it('falls back to the full basic when the period has zero scheduled working days (divisor 0)', function () {
    $e = segEmployee();
    segStructure($e, '2026-06-01', 6000000);

    // Weekend-only period: Sat 4 Jul - Sun 5 Jul 2026.
    $result = app(SalarySegmentationService::class)->compute(
        $e, Carbon::parse('2026-07-04'), Carbon::parse('2026-07-05')
    );

    expect($result['divisor'])->toBe(0);
    expect($result['base_salary'])->toBe('6000000.00');
});
