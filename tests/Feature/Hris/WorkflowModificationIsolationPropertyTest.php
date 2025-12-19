<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 11: Workflow modification isolation
 * Validates: Requirements 6.3
 *
 * For any workflow modification, existing in-progress approvals SHALL continue using
 * their original workflow configuration, and only new requests SHALL use the modified workflow.
 */
test('in-progress approvals use original workflow configuration after workflow modification', function () {
    // Create roles for approvers
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);

    // Create approvers
    $approver1 = User::factory()->create();
    $approver1->assignRole('hr-admin');

    $approver2 = User::factory()->create();
    $approver2->assignRole('head_division');

    $approver3 = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create original workflow with random steps (2-3 steps)
        $originalStepCount = fake()->numberBetween(2, 3);
        $originalSteps = [];
        for ($j = 0; $j < $originalStepCount; $j++) {
            $originalSteps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $j % 2 === 0 ? $approver1->id : $approver2->id,
            ];
        }

        $workflow = ApprovalWorkflow::create([
            'name' => 'Original Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $originalSteps,
            'is_active' => true,
        ]);

        // Create first leave request and initialize workflow (in-progress approval)
        $inProgressRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($inProgressRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Store the original workflow snapshot from the in-progress approval
        $inProgressSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $inProgressRequest->id)
            ->orderBy('step_order')
            ->get();

        $originalSnapshot = $inProgressSteps->first()->workflow_snapshot;

        // Verify snapshot was stored
        expect($originalSnapshot)->not->toBeNull()
            ->and($originalSnapshot['steps'])->toHaveCount($originalStepCount);

        // Now modify the workflow (add more steps, change approvers)
        $modifiedStepCount = fake()->numberBetween(3, 5);
        $modifiedSteps = [];
        for ($j = 0; $j < $modifiedStepCount; $j++) {
            $modifiedSteps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $approver3->id, // Different approver
            ];
        }

        $workflow->update([
            'name' => 'Modified Workflow ' . $i,
            'steps' => $modifiedSteps,
        ]);

        // Create a new leave request after workflow modification
        $newRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($newRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify in-progress approval still uses original configuration
        $inProgressSteps->each(function ($step) {
            $step->refresh();
        });

        // The in-progress approval should still have the original snapshot
        $inProgressSnapshot = $inProgressSteps->first()->workflow_snapshot;
        expect($inProgressSnapshot['steps'])->toHaveCount($originalStepCount)
            ->and($inProgressSnapshot['workflow_name'])->toBe('Original Workflow ' . $i);

        // Verify the in-progress approval steps still reference original approvers
        foreach ($inProgressSteps as $index => $step) {
            $expectedApprover = $index % 2 === 0 ? $approver1->id : $approver2->id;
            expect($step->approver_id)->toBe($expectedApprover);
        }

        // Verify new request uses modified workflow configuration
        $newRequestSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $newRequest->id)
            ->orderBy('step_order')
            ->get();

        expect($newRequestSteps)->toHaveCount($modifiedStepCount);

        $newSnapshot = $newRequestSteps->first()->workflow_snapshot;
        expect($newSnapshot['steps'])->toHaveCount($modifiedStepCount)
            ->and($newSnapshot['workflow_name'])->toBe('Modified Workflow ' . $i);

        // Verify new request steps reference the new approver
        foreach ($newRequestSteps as $step) {
            expect($step->approver_id)->toBe($approver3->id);
        }

        // Clean up for next iteration
        ApprovalStep::where('approvable_id', $inProgressRequest->id)->delete();
        ApprovalStep::where('approvable_id', $newRequest->id)->delete();
        $workflow->delete();
        $inProgressRequest->delete();
        $newRequest->delete();
    }
});

test('workflow snapshot preserves all original step definitions', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrAdmin = User::factory()->create();
    $hrAdmin->assignRole('hr-admin');

    $directUser = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random workflow with mixed step types
        $stepCount = fake()->numberBetween(1, 4);
        $steps = [];
        for ($j = 0; $j < $stepCount; $j++) {
            $stepType = fake()->randomElement(['role', 'user']);
            $steps[] = [
                'order' => $j + 1,
                'type' => $stepType,
                'value' => $stepType === 'role' ? 'hr-admin' : $directUser->id,
            ];
        }

        $workflowName = 'Test Workflow ' . fake()->uuid();
        $workflow = ApprovalWorkflow::create([
            'name' => $workflowName,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get the created approval steps
        $approvalSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->orderBy('step_order')
            ->get();

        // Verify each step has the complete workflow snapshot
        foreach ($approvalSteps as $step) {
            $snapshot = $step->workflow_snapshot;

            expect($snapshot)->not->toBeNull()
                ->and($snapshot)->toHaveKeys(['workflow_id', 'workflow_name', 'for_module', 'steps', 'snapshot_at'])
                ->and($snapshot['workflow_id'])->toBe($workflow->id)
                ->and($snapshot['workflow_name'])->toBe($workflowName)
                ->and($snapshot['for_module'])->toBe(ApprovalWorkflow::MODULE_LEAVE)
                ->and($snapshot['steps'])->toHaveCount($stepCount)
                ->and($snapshot['snapshot_at'])->not->toBeNull();

            // Verify step definitions match exactly
            foreach ($snapshot['steps'] as $index => $snapshotStep) {
                expect($snapshotStep['order'])->toBe($steps[$index]['order'])
                    ->and($snapshotStep['type'])->toBe($steps[$index]['type'])
                    ->and($snapshotStep['value'])->toEqual($steps[$index]['value']);
            }
        }

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('approval progression uses snapshot configuration not current workflow', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $approver1 = User::factory()->create();
    $approver2 = User::factory()->create();
    $approver3 = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with 2 steps
        $workflow = ApprovalWorkflow::create([
            'name' => 'Two Step Workflow',
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver1->id],
                ['order' => 2, 'type' => 'user', 'value' => $approver2->id],
            ],
            'is_active' => true,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Approve first step
        $step1 = $service->getCurrentStep($leaveRequest);
        $service->approve($step1, $approver1, 'Approved step 1');

        // Now modify the workflow to change step 2 approver
        $workflow->update([
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver1->id],
                ['order' => 2, 'type' => 'user', 'value' => $approver3->id], // Changed!
            ],
        ]);

        // Get current step (should be step 2)
        $step2 = $service->getCurrentStep($leaveRequest);

        // Step 2 should still have approver2 (from snapshot), not approver3
        expect($step2->approver_id)->toBe($approver2->id)
            ->and($step2->step_order)->toBe(2);

        // Verify the snapshot still has original configuration
        $snapshot = $step2->workflow_snapshot;
        expect($snapshot['steps'][1]['value'])->toBe($approver2->id);

        // Approve step 2 with original approver (approver2)
        $service->approve($step2, $approver2, 'Approved step 2');

        // Verify approval completed successfully
        $leaveRequest->refresh();
        expect($leaveRequest->status)->toBe('approved');

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('getWorkflowConfiguration returns snapshot when available', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $approver = User::factory()->create();

    $service = new ApprovalWorkflowService();

    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

    $step = $service->getCurrentStep($leaveRequest);

    // Test getWorkflowConfiguration method
    $config = $step->getWorkflowConfiguration();

    expect($config)->not->toBeNull()
        ->and($config)->toHaveKeys(['workflow_id', 'workflow_name', 'for_module', 'steps', 'snapshot_at'])
        ->and($config['workflow_id'])->toBe($workflow->id)
        ->and($config['workflow_name'])->toBe('Test Workflow');

    // Test getWorkflowSteps method
    $steps = $step->getWorkflowSteps();
    expect($steps)->toHaveCount(1)
        ->and($steps[0]['order'])->toBe(1)
        ->and($steps[0]['type'])->toBe('user')
        ->and($steps[0]['value'])->toBe($approver->id);

    // Test hasWorkflowSnapshot method
    expect($step->hasWorkflowSnapshot())->toBeTrue();
});
