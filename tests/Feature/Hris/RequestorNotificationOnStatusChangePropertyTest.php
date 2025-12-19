<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\Notification;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 7: Requestor notification on status change
 * Validates: Requirements 3.3
 *
 * For any approval or rejection action, a notification SHALL be created
 * for the original requestor indicating the new status.
 */
test('requestor receives notification when request is approved', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create requestor user and employee
        $requestorUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $requestorUser->id,
        ]);

        // Create approver
        $approver = User::factory()->create();

        // Create single-step workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $approver->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create leave request for the employee
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Clear notifications for requestor
        Notification::where('user_id', $requestorUser->id)->delete();

        // Approve the request
        $step = $service->getCurrentStep($leaveRequest);
        $service->approve($step, $approver, 'Looks good!');

        // Verify requestor received approval notification
        $notification = Notification::where('user_id', $requestorUser->id)
            ->where('type', 'approval_approved')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data['approvable_type'])->toBe(LeaveRequest::class);
        expect($notification->data['approvable_id'])->toBe($leaveRequest->id);
        expect($notification->data['status'])->toBe('approved');

        // Clean up
        Notification::where('user_id', $requestorUser->id)->delete();
        Notification::where('user_id', $approver->id)->delete();
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
        $employee->delete();
        $approver->delete();
        $requestorUser->delete();
    }
});

test('requestor receives notification when request is rejected with reason', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create requestor user and employee
        $requestorUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $requestorUser->id,
        ]);

        // Create approver
        $approver = User::factory()->create();

        // Create single-step workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $approver->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create leave request for the employee
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Clear notifications for requestor
        Notification::where('user_id', $requestorUser->id)->delete();

        // Generate random rejection reason
        $rejectionReason = fake()->sentence();

        // Reject the request
        $step = $service->getCurrentStep($leaveRequest);
        $service->reject($step, $approver, $rejectionReason);

        // Verify requestor received rejection notification
        $notification = Notification::where('user_id', $requestorUser->id)
            ->where('type', 'approval_rejected')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data['approvable_type'])->toBe(LeaveRequest::class);
        expect($notification->data['approvable_id'])->toBe($leaveRequest->id);
        expect($notification->data['status'])->toBe('rejected');
        expect($notification->data['reason'])->toBe($rejectionReason);
        expect($notification->message)->toContain($rejectionReason);

        // Clean up
        Notification::where('user_id', $requestorUser->id)->delete();
        Notification::where('user_id', $approver->id)->delete();
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
        $employee->delete();
        $approver->delete();
        $requestorUser->delete();
    }
});

test('requestor notification works for reimbursement approvals', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create requestor user and employee
        $requestorUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $requestorUser->id,
        ]);

        // Create approver
        $approver = User::factory()->create();

        // Create single-step workflow for reimbursement
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $approver->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create reimbursement for the employee
        $reimbursement = Reimbursement::factory()->create([
            'employee_id' => $employee->id,
            'status' => Reimbursement::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // Clear notifications for requestor
        Notification::where('user_id', $requestorUser->id)->delete();

        // Randomly approve or reject
        $step = $service->getCurrentStep($reimbursement);
        $shouldApprove = fake()->boolean();

        if ($shouldApprove) {
            $service->approve($step, $approver, 'Approved');
            $expectedType = 'approval_approved';
            $expectedStatus = 'approved';
        } else {
            $reason = fake()->sentence();
            $service->reject($step, $approver, $reason);
            $expectedType = 'approval_rejected';
            $expectedStatus = 'rejected';
        }

        // Verify requestor received notification
        $notification = Notification::where('user_id', $requestorUser->id)
            ->where('type', $expectedType)
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data['approvable_type'])->toBe(Reimbursement::class);
        expect($notification->data['approvable_id'])->toBe($reimbursement->id);
        expect($notification->data['status'])->toBe($expectedStatus);

        // Clean up
        Notification::where('user_id', $requestorUser->id)->delete();
        Notification::where('user_id', $approver->id)->delete();
        ApprovalStep::where('approvable_id', $reimbursement->id)->delete();
        $workflow->delete();
        $reimbursement->delete();
        $employee->delete();
        $approver->delete();
        $requestorUser->delete();
    }
});

test('requestor notification is sent only on final approval in multi-step workflow', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create requestor user and employee
        $requestorUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'user_id' => $requestorUser->id,
        ]);

        // Create multiple approvers
        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();

        // Create two-step workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $approver1->id,
                ],
                [
                    'order' => 2,
                    'type' => 'user',
                    'value' => $approver2->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create leave request for the employee
        $leaveRequest = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Clear notifications for requestor
        Notification::where('user_id', $requestorUser->id)->delete();

        // Approve first step
        $step1 = $service->getCurrentStep($leaveRequest);
        $service->approve($step1, $approver1, 'Step 1 approved');

        // Verify requestor did NOT receive approval notification yet (not final)
        $notificationAfterStep1 = Notification::where('user_id', $requestorUser->id)
            ->where('type', 'approval_approved')
            ->first();
        expect($notificationAfterStep1)->toBeNull();

        // Approve second (final) step
        $step2 = $service->getCurrentStep($leaveRequest);
        $service->approve($step2, $approver2, 'Final approval');

        // Now requestor should receive approval notification
        $notificationAfterStep2 = Notification::where('user_id', $requestorUser->id)
            ->where('type', 'approval_approved')
            ->first();
        expect($notificationAfterStep2)->not->toBeNull();
        expect($notificationAfterStep2->data['status'])->toBe('approved');

        // Clean up
        Notification::where('user_id', $requestorUser->id)->delete();
        Notification::where('user_id', $approver1->id)->delete();
        Notification::where('user_id', $approver2->id)->delete();
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
        $employee->delete();
        $approver1->delete();
        $approver2->delete();
        $requestorUser->delete();
    }
});
