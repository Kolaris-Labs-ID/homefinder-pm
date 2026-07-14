<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Services\Hris\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config()->set('payroll.effective_date_salary_segmentation', true));

function mbEmp(): Employee
{
    return Employee::factory()->create(['hire_date' => '2024-01-01', 'is_active' => true]);
}

function mbStruct(Employee $e, string $d, int $b): SalaryStructure
{
    return SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => $d, 'basic_salary' => $b,
        'allowances_fixed' => [], 'allowances_variable' => [], 'deductions' => [],
        'prorate_enabled' => false, 'bpjs_enabled' => false, 'weekly_allowance_enabled' => false, 'daily_allowance_rate' => 0,
    ]);
}

function julyBatch(): PayrunBatch
{
    return PayrunBatch::factory()->create([
        'period_start_date' => '2026-07-01', 'period_end_date' => '2026-07-31',
        'pay_date' => '2026-08-05', 'status' => PayrunBatch::STATUS_DRAFT,
    ]);
}

it('MANDATORY: mid-period raise eff 13 Jul yields the segmented base, not 7.5M', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-13', 7500000);

    $record = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());

    expect((string) $record->basic_salary)->toBe('6978260.87');
    expect(round((float) $record->basic_salary))->not->toBe(7500000.0);
});

it('snapshots the segment breakdown into details for reconstruction', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-13', 7500000);

    $record = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    $snap = $record->details['base_salary_segmentation'];

    expect($snap['calc_version'])->toBe(1);
    expect($snap['divisor'])->toBe(23);
    expect($snap['base_salary'])->toBe('6978260.87');
    expect($snap['rounding'])->toBe('half-up@2');
    expect($snap['segments'])->toHaveCount(2);
    expect($snap['segments'][0]['salary_structure_id'])->not->toBeNull();

    // Reconstruct base from the snapshot WITHOUT re-reading salary_structures:
    $recomputed = collect($snap['segments'])->reduce(
        fn ($acc, $s) => \App\Support\Money::add($acc, \App\Support\Money::mul($s['basic_salary'], $s['payable_days'])),
        '0.00'
    );
    expect(\App\Support\Money::divideRound($recomputed, $snap['divisor']))->toBe('6978260.87');
});

it('decrease eff 13 Jul is segmented, not whole-month at the lower rate', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 7500000);
    mbStruct($e, '2026-07-13', 6000000);
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    // (7.5M*8 + 6M*15)/23 = 150,000,000/23 = 6,521,739.13
    expect((string) $r->basic_salary)->toBe('6521739.13');
});

it('effective on the final working day (31 Jul) contributes one day', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-31', 7500000); // Fri
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    // (6M*22 + 7.5M*1)/23 = 139,500,000/23 = 6,065,217.39
    expect((string) $r->basic_salary)->toBe('6065217.39');
});

it('a salary effective after the period does not change July (invariant 3)', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-08-01', 7500000);
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    expect((string) $r->basic_salary)->toBe('6000000.00');
});

it('a weekend effective date first takes effect on the next working day (B4)', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-11', 7500000); // Saturday
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    // Weekdays >= 11 Jul are 13..31 (15); 1..10 weekdays = 8 -> same split as 13 Jul.
    expect((string) $r->basic_salary)->toBe('6978260.87');
});

it('a public-holiday effective date is honored date-only and the holiday reduces the divisor (B4/K)', function () {
    \App\Models\Hris\MasterHoliday::create(['date' => '2026-07-13', 'name' => 'Test Holiday', 'is_active' => true]); // Mon
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-13', 7500000);
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    // Divisor 23-1(holiday 13th)=22. Seg1 Jul1-12=8; Seg2 Jul13-31=15-1=14.
    // (6M*8 + 7.5M*14)/22 = 153,000,000/22 = 6,954,545.45
    expect((string) $r->basic_salary)->toBe('6954545.45');
});

it('multiple changes in one period each get their own segment', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    mbStruct($e, '2026-07-08', 6500000); // Wed
    mbStruct($e, '2026-07-20', 7500000); // Mon
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    // weekdays: 1-7 ->5, 8-19 ->8, 20-31 ->10 ; (6M*5 + 6.5M*8 + 7.5M*10)/23 = 157,000,000/23
    expect((string) $r->basic_salary)->toBe('6826086.96');
});

it('missing history fails the calculation loudly (B5/F2)', function () {
    $e = mbEmp();
    mbStruct($e, '2026-07-20', 6000000); // no coverage on 1 Jul
    expect(fn () => app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch()))
        ->toThrow(\App\Exceptions\Hris\PayrollCoverageException::class);
});

it('no salary change equals exactly one full monthly salary (invariant 1, no residue)', function () {
    $e = mbEmp();
    mbStruct($e, '2026-06-01', 6000000);
    $r = app(PayrollCalculationService::class)->calculateEmployee($e, julyBatch());
    expect((string) $r->basic_salary)->toBe('6000000.00');
});
