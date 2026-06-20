<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use Database\Seeders\TrainingApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('fails when no active training workflow exists', function () {
    TrainingRecord::factory()->create(['employee_id' => Employee::factory()->create()->id]);

    $this->artisan('hris:backfill-training-approvals')->assertExitCode(1);
});

it('routes orphaned training records and is idempotent', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    (new TrainingApprovalWorkflowSeeder)->run();

    $record = TrainingRecord::factory()->create(['employee_id' => Employee::factory()->create()->id]);
    expect(ApprovalStep::where('approvable_id', $record->id)->count())->toBe(0);

    $this->artisan('hris:backfill-training-approvals')->assertExitCode(0);

    expect(
        ApprovalStep::where('approvable_type', TrainingRecord::class)
            ->where('approvable_id', $record->id)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->count()
    )->toBe(1);

    $this->artisan('hris:backfill-training-approvals')
        ->expectsOutputToContain('Nothing to backfill')
        ->assertExitCode(0);
});
