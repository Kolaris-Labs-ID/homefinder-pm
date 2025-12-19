<?php

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\Notification;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 5: Approver notification on step advancement
 * Validates: Requirements 3.1
 *
 * For any approval step that becomes pending, a notification SHALL be created
 * for the designated approver within the same transaction.
 */
test('notification is created for approver when step becomes pending on workflow initialization', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create approver user
        $approver = User::factory()->create();

        // Create workflow with first step assigned to the approver
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

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Clear any existing notifications for the approver
        Notification::where('user_id', $approver->id)->delete();

        // Initialize workflow - this should create a notification
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify notification was created for the approver
        $notification = Notification::where('user_id', $approver->id)
            ->where('type', 'approval_pending')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data['approvable_type'])->toBe(LeaveRequest::class);
        expect($notification->data['approvable_id'])->toBe($leaveRequest->id);
        expect($notification->data['step_order'])->toBe(1);

        // Clean up
        Notification::where('user_id', $approver->id)->delete();
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
        $approver->delete();
    }
});

test('notification is created for next approver when step advances after approval', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create two approvers
        $approver1 = User::factory()->create();
        $approver2 = User::factory()->create();

        // Create workflow with two steps
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

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Clear notifications to test step advancement
        Notification::where('user_id', $approver2->id)->delete();

        // Get and approve the first step
        $step1 = $service->getCurrentStep($leaveRequest);
        $service->approve($step1, $approver1, 'Approved');

        // Verify notification was created for the second approver
        $notification = Notification::where('user_id', $approver2->id)
            ->where('type', 'approval_pending')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data['approvable_type'])->toBe(LeaveRequest::class);
        expect($notification->data['approvable_id'])->toBe($leaveRequest->id);
        expect($notification->data['step_order'])->toBe(2);

        // Clean up
        Notification::where('user_id', $approver1->id)->delete();
        Notification::where('user_id', $approver2->id)->delete();
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
        $approver1->delete();
        $approver2->delete();
    }
});

test('notification includes link to approvable item', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        $approver = User::factory()->create();

        // Randomly choose module type
        $moduleType = fake()->randomElement([
            ApprovalWorkflow::MODULE_LEAVE,
            ApprovalWorkflow::MODULE_REIMBURSEMENT,
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => $moduleType,
            'steps' => [
                [
                    'order' => 1,
                    'type' => 'user',
                    'value' => $approver->id,
                ],
            ],
            'is_active' => true,
        ]);

        // Create approvable based on module type
        if ($moduleType === ApprovalWorkflow::MODULE_LEAVE) {
            $approvable = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
        } else {
            $approvable = Reimbursement::factory()->create([
                'status' => Reimbursement::STATUS_PENDING,
            ]);
        }

        // Clear notifications
        Notification::where('user_id', $approver->id)->delete();

        // Initialize workflow
        $service->initializeWorkflow($approvable, $moduleType);

        // Verify notification has approvable_url
        $notification = Notification::where('user_id', $approver->id)
            ->where('type', 'approval_pending')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->data)->toHaveKey('approvable_url');
        // URL should be non-null for supported types
        expect($notification->data['approvable_url'])->not->toBeNull();

        // Clean up
        Notification::where('user_id', $approver->id)->delete();
        ApprovalStep::where('approvable_id', $approvable->id)->delete();
        $workflow->delete();
        $approvable->delete();
        $approver->delete();
    }
});

test('notification is created for multi-step workflows at each advancement', function () {
    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create random number of approvers (2-4)
        $stepCount = fake()->numberBetween(2, 4);
        $approvers = User::factory()->count($stepCount)->create();

        // Build steps
        $steps = [];
        foreach ($approvers as $index => $approver) {
            $steps[] = [
                'order' => $index + 1,
                'type' => 'user',
                'value' => $approver->id,
            ];
        }

        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Clear all notifications
        foreach ($approvers as $approver) {
            Notification::where('user_id', $approver->id)->delete();
        }

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify first approver got notification
        $firstNotification = Notification::where('user_id', $approvers[0]->id)
            ->where('type', 'approval_pending')
            ->first();
        expect($firstNotification)->not->toBeNull();

        // Approve each step and verify next approver gets notification
        for ($stepIndex = 0; $stepIndex < $stepCount - 1; $stepIndex++) {
            $currentStep = $service->getCurrentStep($leaveRequest);
            $service->approve($currentStep, $approvers[$stepIndex], 'Approved');

            // Verify next approver got notification
            $nextNotification = Notification::where('user_id', $approvers[$stepIndex + 1]->id)
                ->where('type', 'approval_pending')
                ->where('data->step_order', $stepIndex + 2)
                ->first();

            expect($nextNotification)->not->toBeNull();
        }

        // Clean up
        foreach ($approvers as $approver) {
            Notification::where('user_id', $approver->id)->delete();
            $approver->delete();
        }
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});
