<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Database\Seeders\TrainingApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
});

it('seeds exactly one active hr-admin training workflow, idempotently', function () {
    (new TrainingApprovalWorkflowSeeder)->run();
    (new TrainingApprovalWorkflowSeeder)->run();

    $workflows = ApprovalWorkflow::where('for_module', ApprovalWorkflow::MODULE_TRAINING)->get();

    expect($workflows)->toHaveCount(1)
        ->and($workflows->first()->is_active)->toBeTrue()
        ->and($workflows->first()->steps)->toBe([
            ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
        ]);
});

it('routes a submitted training record to the hr-admin approval console', function () {
    (new TrainingApprovalWorkflowSeeder)->run();

    $hrAdmin = User::factory()->create();
    $hrAdmin->assignRole('hr-admin');

    $record = TrainingRecord::factory()->create([
        'employee_id' => Employee::factory()->create()->id,
    ]);

    app(ApprovalWorkflowService::class)
        ->initializeWorkflow($record, ApprovalWorkflow::MODULE_TRAINING);

    $pending = app(ApprovalWorkflowService::class)->getPendingApprovalsForUser($hrAdmin);

    expect($pending->where('approvable_id', $record->id))->toHaveCount(1)
        ->and($pending->firstWhere('approvable_id', $record->id)->status)
        ->toBe(ApprovalStep::STATUS_PENDING);
});
