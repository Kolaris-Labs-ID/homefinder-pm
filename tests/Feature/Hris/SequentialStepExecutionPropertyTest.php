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
 * Feature: filament-approvals-integration, Property 4: Sequential step execution
 * Validates: Requirements 2.4
 *
 * For any workflow with multiple steps, approval of step N SHALL advance the workflow
 * to step N+1, and step N+1 SHALL not be actionable until step N is approved.
 */
test('sequential step execution advances workflow correctly after each approval', function () {
    // Create users for approval
    $approvers = User::factory()->count(5)->create();

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random number of steps (2-5)
        $stepCount = fake()->numberBetween(2, 5);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $approvers[$j % count($approvers)]->id,
            ];
        }

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => $steps,
            'is_active' => true,
        ]);

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Verify initial state: only step 1 is pending, others are skipped
        $allSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->orderBy('step_order')
            ->get();

        expect($allSteps)->toHaveCount($stepCount);
        expect($allSteps[0]->status)->toBe(ApprovalStep::STATUS_PENDING);

        for ($k = 1; $k < $stepCount; $k++) {
            expect($allSteps[$k]->status)->toBe(ApprovalStep::STATUS_SKIPPED);
        }

        // Approve each step sequentially and verify progression
        for ($stepIndex = 0; $stepIndex < $stepCount; $stepIndex++) {
            // Get current step
            $currentStep = $service->getCurrentStep($leaveRequest);

            // Verify current step is the expected one
            expect($currentStep)->not->toBeNull();
            expect($currentStep->step_order)->toBe($stepIndex + 1);
            expect($currentStep->status)->toBe(ApprovalStep::STATUS_PENDING);

            // Approve the step
            $approver = $approvers[$stepIndex % count($approvers)];
            $service->approve($currentStep, $approver, "Approved at step " . ($stepIndex + 1));

            // Refresh step
            $currentStep->refresh();

            // Verify step is now approved
            expect($currentStep->status)->toBe(ApprovalStep::STATUS_APPROVED);

            if ($stepIndex < $stepCount - 1) {
                // Not the last step - verify next step is now pending
                $nextStep = $service->getCurrentStep($leaveRequest);
                expect($nextStep)->not->toBeNull();
                expect($nextStep->step_order)->toBe($stepIndex + 2);
                expect($nextStep->status)->toBe(ApprovalStep::STATUS_PENDING);

                // Verify steps after next are still skipped
                $remainingSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
                    ->where('approvable_id', $leaveRequest->id)
                    ->where('step_order', '>', $stepIndex + 2)
                    ->get();

                foreach ($remainingSteps as $remainingStep) {
                    expect($remainingStep->status)->toBe(ApprovalStep::STATUS_SKIPPED);
                }
            } else {
                // Last step - verify no more pending steps
                $nextStep = $service->getCurrentStep($leaveRequest);
                expect($nextStep)->toBeNull();

                // Verify approvable is approved
                $leaveRequest->refresh();
                expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
            }
        }

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('step N+1 is not actionable until step N is approved', function () {
    $approver1 = User::factory()->create();
    $approver2 = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with 2+ steps
        $stepCount = fake()->numberBetween(2, 4);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $j === 0 ? $approver1->id : $approver2->id,
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

        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get step 2 directly (should be skipped)
        $step2 = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->where('step_order', 2)
            ->first();

        // Verify step 2 is skipped (not actionable)
        expect($step2->status)->toBe(ApprovalStep::STATUS_SKIPPED);

        // Try to approve step 2 directly - should fail because it's not pending
        expect(fn() => $service->approve($step2, $approver2))
            ->toThrow(\Exception::class, 'This step has already been processed.');

        // Now approve step 1
        $step1 = $service->getCurrentStep($leaveRequest);
        $service->approve($step1, $approver1);

        // Refresh step 2
        $step2->refresh();

        // Now step 2 should be pending (actionable)
        expect($step2->status)->toBe(ApprovalStep::STATUS_PENDING);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});

test('sequential step execution works for reimbursement approvables', function () {
    $approvers = User::factory()->count(3)->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random number of steps (2-3)
        $stepCount = fake()->numberBetween(2, 3);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $approvers[$j]->id,
            ];
        }

        // Create workflow for reimbursement
        $workflow = ApprovalWorkflow::create([
            'name' => fake()->words(3, true),
            'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
            'steps' => $steps,
            'is_active' => true,
        ]);

        // Create reimbursement
        $reimbursement = Reimbursement::factory()->create([
            'status' => Reimbursement::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // Verify steps were created
        $allSteps = ApprovalStep::where('approvable_type', Reimbursement::class)
            ->where('approvable_id', $reimbursement->id)
            ->orderBy('step_order')
            ->get();

        expect($allSteps)->toHaveCount($stepCount);

        // Approve all steps sequentially
        for ($stepIndex = 0; $stepIndex < $stepCount; $stepIndex++) {
            $currentStep = $service->getCurrentStep($reimbursement);

            expect($currentStep)->not->toBeNull();
            expect($currentStep->step_order)->toBe($stepIndex + 1);

            $service->approve($currentStep, $approvers[$stepIndex]);

            $currentStep->refresh();
            expect($currentStep->status)->toBe(ApprovalStep::STATUS_APPROVED);
        }

        // Verify reimbursement is approved
        $reimbursement->refresh();
        expect($reimbursement->status)->toBe(Reimbursement::STATUS_APPROVED);

        // Clean up
        ApprovalStep::where('approvable_id', $reimbursement->id)->delete();
        $workflow->delete();
        $reimbursement->delete();
    }
});

test('rejection at any step prevents subsequent steps from becoming actionable', function () {
    $approvers = User::factory()->count(4)->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with 3-4 steps
        $stepCount = fake()->numberBetween(3, 4);
        $steps = [];

        for ($j = 0; $j < $stepCount; $j++) {
            $steps[] = [
                'order' => $j + 1,
                'type' => 'user',
                'value' => $approvers[$j]->id,
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

        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Randomly decide at which step to reject (1 to stepCount)
        $rejectAtStep = fake()->numberBetween(1, $stepCount);

        // Approve steps until rejection point
        for ($stepIndex = 1; $stepIndex < $rejectAtStep; $stepIndex++) {
            $currentStep = $service->getCurrentStep($leaveRequest);
            $service->approve($currentStep, $approvers[$stepIndex - 1]);
        }

        // Reject at the chosen step
        $stepToReject = $service->getCurrentStep($leaveRequest);
        $service->reject($stepToReject, $approvers[$rejectAtStep - 1], 'Rejected for testing');

        // Verify no more pending steps exist
        $pendingStep = $service->getCurrentStep($leaveRequest);
        expect($pendingStep)->toBeNull();

        // Verify all subsequent steps remain skipped
        $subsequentSteps = ApprovalStep::where('approvable_type', LeaveRequest::class)
            ->where('approvable_id', $leaveRequest->id)
            ->where('step_order', '>', $rejectAtStep)
            ->get();

        foreach ($subsequentSteps as $step) {
            expect($step->status)->toBe(ApprovalStep::STATUS_SKIPPED);
        }

        // Verify approvable is rejected
        $leaveRequest->refresh();
        expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_REJECTED);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $workflow->delete();
        $leaveRequest->delete();
    }
});
