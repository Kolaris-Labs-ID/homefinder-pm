<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 12: Active workflow selection
 * Validates: Requirements 6.4
 *
 * For any new approvable item, the system SHALL initialize the workflow using only
 * the active workflow for that module, ignoring inactive workflows.
 */
test('new approvable items only use active workflows', function () {
    // Create roles for approvers
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $activeApprover = User::factory()->create();
    $activeApprover->assignRole('hr-admin');

    $inactiveApprover = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create multiple inactive workflows
        $inactiveCount = fake()->numberBetween(1, 3);
        $inactiveWorkflows = [];

        for ($j = 0; $j < $inactiveCount; $j++) {
            $inactiveWorkflows[] = ApprovalWorkflow::create([
                'name' => 'Inactive Workflow ' . $j,
                'for_module' => ApprovalWorkflow::MODULE_LEAVE,
                'steps' => [
                    ['order' => 1, 'type' => 'user', 'value' => $inactiveApprover->id],
                ],
                'is_active' => false,
            ]);
        }

        // Create one active workflow with different configuration
        $activeStepCount = fake()->numberBetween(1, 3);
        $activeSteps = [];
        for ($j = 0; $j < $activeStepCount; $j++) {
            $activeSteps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $activeApprover->id,
            ];
        }

        $activeWorkflow = ApprovalWorkflow::create([
            'name' => 'Active Workflow',
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $activeSteps,
            'is_active' => true,
        ]);

        // Create a new leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get created approval steps
        $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->orderBy('step_order')
            ->get();

        // Verify the active workflow was used
        expect($createdSteps)->toHaveCount($activeStepCount);

        // Verify all steps reference the active workflow
        foreach ($createdSteps as $step) {
            expect($step->workflow_id)->toBe($activeWorkflow->id)
                ->and($step->approver_id)->toBe($activeApprover->id);

            // Verify snapshot references active workflow
            $snapshot = $step->workflow_snapshot;
            expect($snapshot['workflow_id'])->toBe($activeWorkflow->id)
                ->and($snapshot['workflow_name'])->toBe('Active Workflow');
        }

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        foreach ($inactiveWorkflows as $workflow) {
            $workflow->delete();
        }
        $activeWorkflow->delete();
        $leaveRequest->delete();
    }
});

test('no approval steps created when only inactive workflows exist', function () {
    $inactiveApprover = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create only inactive workflows
        $inactiveCount = fake()->numberBetween(1, 3);
        $inactiveWorkflows = [];

        for ($j = 0; $j < $inactiveCount; $j++) {
            $inactiveWorkflows[] = ApprovalWorkflow::create([
                'name' => 'Inactive Workflow ' . $j,
                'for_module' => ApprovalWorkflow::MODULE_LEAVE,
                'steps' => [
                    ['order' => 1, 'type' => 'user', 'value' => $inactiveApprover->id],
                    ['order' => 2, 'type' => 'user', 'value' => $inactiveApprover->id],
                ],
                'is_active' => false,
            ]);
        }

        // Create a new leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow (should do nothing since no active workflow)
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify no approval steps were created
        $createdSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->get();

        expect($createdSteps)->toHaveCount(0);

        // Clean up
        foreach ($inactiveWorkflows as $workflow) {
            $workflow->delete();
        }
        $leaveRequest->delete();
    }
});

test('getActiveWorkflowForModule returns only active workflow', function () {
    $approver = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create inactive workflows
        $inactiveCount = fake()->numberBetween(1, 3);
        $inactiveWorkflows = [];

        for ($j = 0; $j < $inactiveCount; $j++) {
            $inactiveWorkflows[] = ApprovalWorkflow::create([
                'name' => 'Inactive ' . $j,
                'for_module' => ApprovalWorkflow::MODULE_LEAVE,
                'steps' => [['order' => 1, 'type' => 'user', 'value' => $approver->id]],
                'is_active' => false,
            ]);
        }

        // Create active workflow
        $activeWorkflow = ApprovalWorkflow::create([
            'name' => 'Active Workflow',
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [['order' => 1, 'type' => 'user', 'value' => $approver->id]],
            'is_active' => true,
        ]);

        // Test getActiveWorkflowForModule
        $result = $service->getActiveWorkflowForModule(ApprovalWorkflow::MODULE_LEAVE);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($activeWorkflow->id)
            ->and($result->is_active)->toBeTrue()
            ->and($result->name)->toBe('Active Workflow');

        // Clean up
        foreach ($inactiveWorkflows as $workflow) {
            $workflow->delete();
        }
        $activeWorkflow->delete();
    }
});

test('getActiveWorkflowForModule returns null when no active workflow exists', function () {
    $approver = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Create only inactive workflows
        $inactiveCount = fake()->numberBetween(0, 3);
        $inactiveWorkflows = [];

        for ($j = 0; $j < $inactiveCount; $j++) {
            $inactiveWorkflows[] = ApprovalWorkflow::create([
                'name' => 'Inactive ' . $j,
                'for_module' => ApprovalWorkflow::MODULE_LEAVE,
                'steps' => [['order' => 1, 'type' => 'user', 'value' => $approver->id]],
                'is_active' => false,
            ]);
        }

        // Test getActiveWorkflowForModule
        $result = $service->getActiveWorkflowForModule(ApprovalWorkflow::MODULE_LEAVE);

        expect($result)->toBeNull();

        // Clean up
        foreach ($inactiveWorkflows as $workflow) {
            $workflow->delete();
        }
    }
});

test('active workflow selection respects module type', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $leaveApprover = User::factory()->create();
    $leaveApprover->assignRole('hr-admin');

    $reimbursementApprover = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create active workflow for leave module
        $leaveWorkflow = ApprovalWorkflow::create([
            'name' => 'Leave Workflow',
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $leaveApprover->id],
            ],
            'is_active' => true,
        ]);

        // Create active workflow for reimbursement module
        $reimbursementWorkflow = ApprovalWorkflow::create([
            'name' => 'Reimbursement Workflow',
            'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $reimbursementApprover->id],
                ['order' => 2, 'type' => 'user', 'value' => $reimbursementApprover->id],
            ],
            'is_active' => true,
        ]);

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Create reimbursement
        $reimbursement = Reimbursement::factory()->create([
            'status' => 'pending',
        ]);
        $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // Verify leave request uses leave workflow
        $leaveSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->get();

        expect($leaveSteps)->toHaveCount(1)
            ->and($leaveSteps->first()->workflow_id)->toBe($leaveWorkflow->id)
            ->and($leaveSteps->first()->approver_id)->toBe($leaveApprover->id);

        // Verify reimbursement uses reimbursement workflow
        $reimbursementSteps = ApprovalStep::where('approvable_type', Reimbursement::class)
            ->where('approvable_id', $reimbursement->id)
            ->get();

        expect($reimbursementSteps)->toHaveCount(2)
            ->and($reimbursementSteps->first()->workflow_id)->toBe($reimbursementWorkflow->id)
            ->and($reimbursementSteps->first()->approver_id)->toBe($reimbursementApprover->id);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        ApprovalStep::where('approvable_id', $reimbursement->id)->delete();
        $leaveWorkflow->delete();
        $reimbursementWorkflow->delete();
        $leaveRequest->delete();
        $reimbursement->delete();
    }
});

test('deactivating workflow prevents its use for new requests', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create active workflow
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test Workflow',
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create first leave request (should use workflow)
        $leaveRequest1 = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest1, ApprovalWorkflow::MODULE_LEAVE);

        // Verify first request has approval steps
        $steps1 = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest1->id)
            ->get();
        expect($steps1)->toHaveCount(1);

        // Deactivate the workflow
        $workflow->update(['is_active' => false]);

        // Create second leave request (should NOT use workflow)
        $leaveRequest2 = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest2, ApprovalWorkflow::MODULE_LEAVE);

        // Verify second request has NO approval steps
        $steps2 = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest2->id)
            ->get();
        expect($steps2)->toHaveCount(0);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest1->id)->delete();
        $workflow->delete();
        $leaveRequest1->delete();
        $leaveRequest2->delete();
    }
});
