<?php

/**
 * Feature: filament-approvals-integration, Property 10: Bulk approval atomicity
 * Validates: Requirements 5.4
 *
 * For any bulk approval action on N items, either all N items SHALL be approved
 * successfully, or none SHALL be modified (transaction rollback on failure).
 */

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('bulk approval succeeds when all items are valid', function () {
    // Create role
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create random number of leave requests (2-5)
        $count = fake()->numberBetween(2, 5);
        $leaveRequests = [];
        $stepIds = [];

        for ($j = 0; $j < $count; $j++) {
            $leaveRequest = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
            $leaveRequests[] = $leaveRequest;

            $step = $service->getCurrentStep($leaveRequest);
            $stepIds[] = $step->id;
        }

        // Perform bulk approval
        $result = $service->bulkApprove($stepIds, $approver, 'Bulk approved');

        // Should succeed
        expect($result['success'])->toBeTrue()
            ->and(count($result['results']))->toBe($count);

        // All steps should be approved
        foreach ($stepIds as $stepId) {
            $step = ApprovalStep::find($stepId);
            expect($step->status)->toBe(ApprovalStep::STATUS_APPROVED);
        }

        // All leave requests should be approved (single-step workflow)
        foreach ($leaveRequests as $leaveRequest) {
            $leaveRequest->refresh();
            expect($leaveRequest->status)->toBe('approved');
        }

        // Clean up
        ApprovalStep::whereIn('id', $stepIds)->delete();
        foreach ($leaveRequests as $lr) {
            $lr->delete();
        }
        $workflow->delete();
    }
});

test('bulk approval rolls back all changes when one item fails', function () {
    // Create role
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    // Create another user who is NOT an approver
    $otherUser = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with approver as the designated user
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create workflow with other user as approver (will fail for our approver)
        $otherWorkflow = ApprovalWorkflow::create([
            'name' => 'Other Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $otherUser->id],
            ],
            'is_active' => false, // Inactive so it won't be used automatically
        ]);

        // Create valid leave requests
        $validCount = fake()->numberBetween(2, 4);
        $leaveRequests = [];
        $stepIds = [];

        for ($j = 0; $j < $validCount; $j++) {
            $leaveRequest = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
            $leaveRequests[] = $leaveRequest;

            $step = $service->getCurrentStep($leaveRequest);
            $stepIds[] = $step->id;
        }

        // Create one leave request with a step that the approver cannot approve
        $invalidLeaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Manually create a step with the other user as approver
        $invalidStep = ApprovalStep::create([
            'workflow_id' => $otherWorkflow->id,
            'approvable_type' => LeaveRequest::class,
            'approvable_id' => $invalidLeaveRequest->id,
            'step_order' => 1,
            'approver_id' => $otherUser->id,
            'approver_role' => null,
            'status' => ApprovalStep::STATUS_PENDING,
        ]);

        $leaveRequests[] = $invalidLeaveRequest;
        $stepIds[] = $invalidStep->id;

        // Record original states
        $originalStates = [];
        foreach ($stepIds as $stepId) {
            $step = ApprovalStep::find($stepId);
            $originalStates[$stepId] = $step->status;
        }

        // Perform bulk approval - should fail because of the invalid step
        $result = $service->bulkApprove($stepIds, $approver, 'Bulk approved');

        // Should fail
        expect($result['success'])->toBeFalse();

        // All steps should remain in their original state (rollback)
        foreach ($stepIds as $stepId) {
            $step = ApprovalStep::find($stepId);
            expect($step->status)->toBe($originalStates[$stepId]);
        }

        // All leave requests should remain pending
        foreach ($leaveRequests as $leaveRequest) {
            $leaveRequest->refresh();
            expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
        }

        // Clean up
        ApprovalStep::whereIn('id', $stepIds)->delete();
        foreach ($leaveRequests as $lr) {
            $lr->delete();
        }
        $workflow->delete();
        $otherWorkflow->delete();
    }
});

test('bulk approval with non-existent step ID fails atomically', function () {
    // Create role
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();

    // Create workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Test Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    // Create valid leave requests
    $leaveRequests = [];
    $stepIds = [];

    for ($j = 0; $j < 3; $j++) {
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
        $leaveRequests[] = $leaveRequest;

        $step = $service->getCurrentStep($leaveRequest);
        $stepIds[] = $step->id;
    }

    // Add a non-existent step ID
    $stepIds[] = 999999;

    // Perform bulk approval
    $result = $service->bulkApprove($stepIds, $approver, 'Bulk approved');

    // Should fail
    expect($result['success'])->toBeFalse();

    // All valid steps should remain pending (rollback)
    foreach (array_slice($stepIds, 0, 3) as $stepId) {
        $step = ApprovalStep::find($stepId);
        expect($step->status)->toBe(ApprovalStep::STATUS_PENDING);
    }

    // All leave requests should remain pending
    foreach ($leaveRequests as $leaveRequest) {
        $leaveRequest->refresh();
        expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
    }
});
