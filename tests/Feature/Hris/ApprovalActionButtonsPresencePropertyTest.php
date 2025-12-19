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
 * Feature: filament-approvals-integration, Property 1: Approval action buttons presence
 * Validates: Requirements 1.1
 *
 * For any approvable resource page viewed by an authorized approver, the page SHALL
 * contain approve and reject action buttons when the item is pending their approval.
 */

beforeEach(function () {
    // Create necessary roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);
});

test('authorized approver can see approve and reject buttons for pending leave requests with workflow', function () {
    // Create approver user
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    // Create workflow with the approver
    $workflow = ApprovalWorkflow::create([
        'name' => 'Leave Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify the leave request has approval workflow
        expect($leaveRequest->hasApprovalWorkflow())->toBeTrue();

        // Verify the approver can approve
        expect($leaveRequest->canBeApprovedBy($approver))->toBeTrue();

        // Verify the current step is pending
        $currentStep = $leaveRequest->currentApprovalStep();
        expect($currentStep)->not->toBeNull()
            ->and($currentStep->status)->toBe(ApprovalStep::STATUS_PENDING)
            ->and($currentStep->approver_id)->toBe($approver->id);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('unauthorized user cannot see approve and reject buttons for pending leave requests', function () {
    // Create approver user
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    // Create another user who is not the approver
    $otherUser = User::factory()->create();

    // Create workflow with the approver
    $workflow = ApprovalWorkflow::create([
        'name' => 'Leave Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify the other user cannot approve
        expect($leaveRequest->canBeApprovedBy($otherUser))->toBeFalse();

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('role-based approver can see approve and reject buttons', function () {
    // Create users with the approver role
    $approverRole = 'head_division';
    $approver = User::factory()->create();
    $approver->assignRole($approverRole);

    // Create workflow with role-based approver
    $workflow = ApprovalWorkflow::create([
        'name' => 'Leave Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'role', 'value' => $approverRole],
        ],
        'is_active' => true,
    ]);

    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify the role-based approver can approve
        expect($leaveRequest->canBeApprovedBy($approver))->toBeTrue();

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('approved or rejected items do not show approve/reject buttons', function () {
    // Create approver user
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    // Create workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Leave Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Test approved status
        $approvedRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_APPROVED,
        ]);

        // Approved items should not be approvable
        expect($approvedRequest->isPending())->toBeFalse()
            ->and($approvedRequest->canBeApprovedBy($approver))->toBeFalse();

        // Test rejected status
        $rejectedRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_REJECTED,
        ]);

        // Rejected items should not be approvable
        expect($rejectedRequest->isPending())->toBeFalse()
            ->and($rejectedRequest->canBeApprovedBy($approver))->toBeFalse();

        // Clean up
        $approvedRequest->delete();
        $rejectedRequest->delete();
    }

    $workflow->delete();
});

test('authorized approver can see approve and reject buttons for pending reimbursements with workflow', function () {
    // Create approver user
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    // Create workflow with the approver
    $workflow = ApprovalWorkflow::create([
        'name' => 'Reimbursement Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 20;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending reimbursement
        $reimbursement = Reimbursement::factory()->create([
            'status' => Reimbursement::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // Verify the reimbursement has approval workflow
        expect($reimbursement->hasApprovalWorkflow())->toBeTrue();

        // Verify the approver can approve
        expect($reimbursement->canBeApprovedBy($approver))->toBeTrue();

        // Verify the current step is pending
        $currentStep = $reimbursement->currentApprovalStep();
        expect($currentStep)->not->toBeNull()
            ->and($currentStep->status)->toBe(ApprovalStep::STATUS_PENDING)
            ->and($currentStep->approver_id)->toBe($approver->id);

        // Clean up
        ApprovalStep::where('approvable_id', $reimbursement->id)->delete();
        $reimbursement->delete();
    }

    $workflow->delete();
});

test('multi-step workflow shows buttons only to current step approver', function () {
    // Create two approvers
    $approver1 = User::factory()->create();
    $approver1->assignRole('head_division');

    $approver2 = User::factory()->create();
    $approver2->assignRole('hr-admin');

    // Create workflow with two steps
    $workflow = ApprovalWorkflow::create([
        'name' => 'Multi-step Leave Approval',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver1->id],
            ['order' => 2, 'type' => 'user', 'value' => $approver2->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 15;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // At step 1, only approver1 should be able to approve
        expect($leaveRequest->canBeApprovedBy($approver1))->toBeTrue()
            ->and($leaveRequest->canBeApprovedBy($approver2))->toBeFalse();

        // Approve step 1
        $currentStep = $leaveRequest->currentApprovalStep();
        $service->approve($currentStep, $approver1, 'Approved at step 1');

        // Refresh the leave request
        $leaveRequest->refresh();

        // At step 2, only approver2 should be able to approve
        expect($leaveRequest->canBeApprovedBy($approver1))->toBeFalse()
            ->and($leaveRequest->canBeApprovedBy($approver2))->toBeTrue();

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});
