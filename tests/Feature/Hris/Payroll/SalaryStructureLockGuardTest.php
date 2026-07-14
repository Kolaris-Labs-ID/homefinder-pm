<?php

use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lockedJuly(): PayrunBatch
{
    return PayrunBatch::factory()->create([
        'period_start_date' => '2026-07-01', 'period_end_date' => '2026-07-31',
        'status' => PayrunBatch::STATUS_PAID,
    ]);
}

it('blocks creating a salary structure whose effective date intersects a paid period', function () {
    lockedJuly();
    $e = Employee::factory()->create(['is_active' => true]);

    expect(fn () => SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => '2026-07-13', 'basic_salary' => 7500000,
    ]))->toThrow(\App\Exceptions\Hris\PayrollLockedException::class);
});

it('blocks deleting a salary structure that a paid period depends on', function () {
    lockedJuly();
    $e = Employee::factory()->create(['is_active' => true]);
    $s = SalaryStructure::withoutEvents(fn () => SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => '2026-07-10', 'basic_salary' => 6000000,
    ]));

    expect(fn () => $s->delete())->toThrow(\App\Exceptions\Hris\PayrollLockedException::class);
});

it('marks a calculated (non-locked) draft stale when an intersecting salary changes', function () {
    $batch = PayrunBatch::factory()->create([
        'period_start_date' => '2026-07-01', 'period_end_date' => '2026-07-31',
        'status' => PayrunBatch::STATUS_CALCULATED, 'calculated_at' => now(),
    ]);
    $e = Employee::factory()->create(['is_active' => true]);

    SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => '2026-07-13', 'basic_salary' => 7500000,
    ]);

    $batch->refresh();
    expect($batch->status)->toBe(PayrunBatch::STATUS_DRAFT);
    expect($batch->calculated_at)->toBeNull();
});

it('allows a salary change that does not intersect any locked or calculated period', function () {
    $e = Employee::factory()->create(['is_active' => true]);
    expect(SalaryStructure::factory()->create([
        'employee_id' => $e->id, 'effective_date' => '2026-09-01', 'basic_salary' => 7500000,
    ]))->toBeInstanceOf(SalaryStructure::class);
});
