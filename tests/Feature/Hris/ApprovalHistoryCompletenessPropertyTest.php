<?php

use App\Livewire\ApprovalHistory;
use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Feature: filament-approvals-integration, Property 2: Approval history completeness
 * Validates: Requirements 1.4
 *
 * For any approval step that has been processed (approved or rejected), the history
 * display SHALL include the approver name, timestamp, and notes.
 */

beforeEach(function () {
    // Create necessary roles
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'head_division', 'guard_name' => 'web']);
});

test('approved steps include approver name, timestamp, and notes in history', function () {
    // Create approver user
    $approver = User::factory()->create(['name' => 'Test Approver']);
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

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random notes
        $notes = fake()->optional(0.7)->sentence();

        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Approve the step
        $currentStep = $leaveRequest->currentApprovalStep();
        $service->approve($currentStep, $approver, $notes);

        // Refresh the leave request
        $leaveRequest->refresh();

        // Get approval history
        $history = $leaveRequest->getApprovalHistory();

        // Verify history is not empty
        expect($history)->not->toBeEmpty();

        // Find the approved step in history
        $approvedStep = $history->firstWhere('status', ApprovalStep::STATUS_APPROVED);

        // Property: Approved step must include approver name
        expect($approvedStep['approver_name'])->toBe($approver->name);

        // Property: Approved step must include timestamp
        expect($approvedStep['approved_at'])->not->toBeNull();

        // Property: Approved step must include notes (even if null)
        expect(array_key_exists('notes', $approvedStep))->toBeTrue();
        expect($approvedStep['notes'])->toBe($notes);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('rejected steps include approver name, timestamp, and rejection reason in history', function () {
    // Create approver user
    $approver = User::factory()->create(['name' => 'Test Rejector']);
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

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random rejection reason
        $reason = fake()->sentence();

        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Reject the step
        $currentStep = $leaveRequest->currentApprovalStep();
        $service->reject($currentStep, $approver, $reason);

        // Refresh the leave request
        $leaveRequest->refresh();

        // Get approval history
        $history = $leaveRequest->getApprovalHistory();

        // Verify history is not empty
        expect($history)->not->toBeEmpty();

        // Find the rejected step in history
        $rejectedStep = $history->firstWhere('status', ApprovalStep::STATUS_REJECTED);

        // Property: Rejected step must include approver name
        expect($rejectedStep['approver_name'])->toBe($approver->name);

        // Property: Rejected step must include timestamp
        expect($rejectedStep['approved_at'])->not->toBeNull();

        // Property: Rejected step must include rejection reason in notes
        expect($rejectedStep['notes'])->toBe($reason);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('multi-step workflow history includes all processed steps with complete information', function () {
    // Create two approvers
    $approver1 = User::factory()->create(['name' => 'First Approver']);
    $approver1->assignRole('head_division');

    $approver2 = User::factory()->create(['name' => 'Second Approver']);
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

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random notes for each step
        $notes1 = fake()->optional(0.7)->sentence();
        $notes2 = fake()->optional(0.7)->sentence();

        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Approve step 1
        $step1 = $leaveRequest->currentApprovalStep();
        $service->approve($step1, $approver1, $notes1);

        // Refresh and approve step 2
        $leaveRequest->refresh();
        $step2 = $leaveRequest->currentApprovalStep();
        $service->approve($step2, $approver2, $notes2);

        // Refresh the leave request
        $leaveRequest->refresh();

        // Get approval history
        $history = $leaveRequest->getApprovalHistory();

        // Property: History should contain all steps
        expect($history)->toHaveCount(2);

        // Property: Each step should have complete information
        foreach ($history as $step) {
            // Must have approver name
            expect($step['approver_name'])->not->toBeNull();

            // Must have timestamp for processed steps
            if ($step['status'] === ApprovalStep::STATUS_APPROVED) {
                expect($step['approved_at'])->not->toBeNull();
            }

            // Must have notes key
            expect(array_key_exists('notes', $step))->toBeTrue();
        }

        // Verify step order
        expect($history[0]['step_order'])->toBe(1);
        expect($history[1]['step_order'])->toBe(2);

        // Verify approver names match
        expect($history[0]['approver_name'])->toBe($approver1->name);
        expect($history[1]['approver_name'])->toBe($approver2->name);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});

test('reimbursement approval history includes complete information', function () {
    // Create approver user
    $approver = User::factory()->create(['name' => 'Reimbursement Approver']);
    $approver->assignRole('hr-admin');

    // Create workflow
    $workflow = ApprovalWorkflow::create([
        'name' => 'Reimbursement Approval Workflow',
        'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
        'steps' => [
            ['order' => 1, 'type' => 'user', 'value' => $approver->id],
        ],
        'is_active' => true,
    ]);

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Generate random notes
        $notes = fake()->optional(0.7)->sentence();

        // Create a pending reimbursement
        $reimbursement = Reimbursement::factory()->create([
            'status' => Reimbursement::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // Approve the step
        $currentStep = $reimbursement->currentApprovalStep();
        $service->approve($currentStep, $approver, $notes);

        // Refresh the reimbursement
        $reimbursement->refresh();

        // Get approval history
        $history = $reimbursement->getApprovalHistory();

        // Verify history is not empty
        expect($history)->not->toBeEmpty();

        // Find the approved step in history
        $approvedStep = $history->firstWhere('status', ApprovalStep::STATUS_APPROVED);

        // Property: Approved step must include approver name
        expect($approvedStep['approver_name'])->toBe($approver->name);

        // Property: Approved step must include timestamp
        expect($approvedStep['approved_at'])->not->toBeNull();

        // Property: Approved step must include notes
        expect(array_key_exists('notes', $approvedStep))->toBeTrue();
        expect($approvedStep['notes'])->toBe($notes);

        // Clean up
        ApprovalStep::where('approvable_id', $reimbursement->id)->delete();
        $reimbursement->delete();
    }

    $workflow->delete();
});

test('role-based approver history includes role information when user not assigned', function () {
    // Create workflow with role-based approver
    $workflow = ApprovalWorkflow::create([
        'name' => 'Role-based Leave Approval',
        'for_module' => ApprovalWorkflow::MODULE_LEAVE,
        'steps' => [
            ['order' => 1, 'type' => 'role', 'value' => 'head_division'],
        ],
        'is_active' => true,
    ]);

    $iterations = 100;

    for ($i = 0; $i < $iterations; $i++) {
        // Create a pending leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        // Initialize workflow
        $service = app(ApprovalWorkflowService::class);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get approval history (before approval)
        $history = $leaveRequest->getApprovalHistory();

        // Verify history is not empty
        expect($history)->not->toBeEmpty();

        // Find the pending step
        $pendingStep = $history->firstWhere('status', ApprovalStep::STATUS_PENDING);

        // Property: Role-based step should have approver_role set
        expect($pendingStep['approver_role'])->toBe('head_division');

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)->delete();
        $leaveRequest->delete();
    }

    $workflow->delete();
});
