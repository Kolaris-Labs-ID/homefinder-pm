<?php

use App\Filament\Resources\PayrunBatchResource\Pages\ListPayrunBatches;
use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('payroll.effective_date_salary_segmentation', true);
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole($role);
    $this->actingAs($this->admin);
});

it('surfaces a coverage gap as a danger notification instead of a 500', function () {
    Employee::factory()->create(['hire_date' => '2024-06-26', 'is_active' => true]);

    $batch = PayrunBatch::create([
        'period_start_date' => '2026-06-26',
        'period_end_date' => '2026-07-17',
        'pay_date' => '2026-08-03',
        'status' => PayrunBatch::STATUS_DRAFT,
    ]);

    Livewire::test(ListPayrunBatches::class)
        ->callTableAction('calculate', $batch)
        ->assertNotified('Calculation Failed');

    expect($batch->fresh()->status)->toBe(PayrunBatch::STATUS_DRAFT)
        ->and($batch->fresh()->payrollRecords()->count())->toBe(0);
});
