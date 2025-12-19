<?php

/**
 * Feature: filament-approvals-integration, Property 9: Module filter correctness
 * Validates: Requirements 5.2
 *
 * For any module filter applied to pending approvals, the returned items
 * SHALL only include approvables of that module type.
 */

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('module filter returns only items of the specified module type', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver user with role
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 50;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflows for different modules
        $leaveWorkflow = ApprovalWorkflow::create([
            'name' => 'Leave Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        $reimbursementWorkflow = ApprovalWorkflow::create([
            'name' => 'Reimbursement Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create random number of leave requests and reimbursements
        $leaveCount = fake()->numberBetween(1, 5);
        $reimbursementCount = fake()->numberBetween(1, 5);

        $leaveRequests = [];
        $reimbursements = [];

        for ($j = 0; $j < $leaveCount; $j++) {
            $leaveRequest = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
            $leaveRequests[] = $leaveRequest;
        }

        for ($j = 0; $j < $reimbursementCount; $j++) {
            $reimbursement = Reimbursement::factory()->create([
                'status' => Reimbursement::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);
            $reimbursements[] = $reimbursement;
        }

        // Test filtering by leave module
        $leaveApprovals = $service->getPendingApprovalsByModule($approver, ApprovalWorkflow::MODULE_LEAVE);

        // All returned items should be LeaveRequest type
        foreach ($leaveApprovals as $step) {
            expect($step->approvable_type)->toBe(LeaveRequest::class);
        }

        // Should have exactly the number of leave requests we created
        expect($leaveApprovals)->toHaveCount($leaveCount);

        // Test filtering by reimbursement module
        $reimbursementApprovals = $service->getPendingApprovalsByModule($approver, ApprovalWorkflow::MODULE_REIMBURSEMENT);

        // All returned items should be Reimbursement type
        foreach ($reimbursementApprovals as $step) {
            expect($step->approvable_type)->toBe(Reimbursement::class);
        }

        // Should have exactly the number of reimbursements we created
        expect($reimbursementApprovals)->toHaveCount($reimbursementCount);

        // Clean up for next iteration
        ApprovalStep::whereIn('approvable_id', collect($leaveRequests)->pluck('id'))
            ->where('approvable_type', LeaveRequest::class)
            ->delete();
        ApprovalStep::whereIn('approvable_id', collect($reimbursements)->pluck('id'))
            ->where('approvable_type', Reimbursement::class)
            ->delete();

        foreach ($leaveRequests as $lr) {
            $lr->delete();
        }
        foreach ($reimbursements as $r) {
            $r->delete();
        }

        $leaveWorkflow->delete();
        $reimbursementWorkflow->delete();
    }
});

test('module filter returns empty collection for invalid module', function () {
    $approver = User::factory()->create();
    $service = new ApprovalWorkflowService();

    $result = $service->getPendingApprovalsByModule($approver, 'invalid_module');

    expect($result)->toBeEmpty();
});

test('module filter respects user authorization', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'finance-admin', 'guard_name' => 'web']);

    // Create two approvers with different roles
    $hrApprover = User::factory()->create();
    $hrApprover->assignRole('hr-admin');

    $financeApprover = User::factory()->create();
    $financeApprover->assignRole('finance-admin');

    $otherUser = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 30;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with role-based approver
        $workflow = ApprovalWorkflow::create([
            'name' => 'Leave Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
            ],
            'is_active' => true,
        ]);

        // Create leave request
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // HR approver should see the approval
        $hrApprovals = $service->getPendingApprovalsByModule($hrApprover, ApprovalWorkflow::MODULE_LEAVE);
        expect($hrApprovals)->toHaveCount(1);

        // Finance approver should NOT see the approval (different role)
        $financeApprovals = $service->getPendingApprovalsByModule($financeApprover, ApprovalWorkflow::MODULE_LEAVE);
        expect($financeApprovals)->toHaveCount(0);

        // Other user without any role should NOT see the approval
        $otherApprovals = $service->getPendingApprovalsByModule($otherUser, ApprovalWorkflow::MODULE_LEAVE);
        expect($otherApprovals)->toHaveCount(0);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)
            ->where('approvable_type', LeaveRequest::class)
            ->delete();
        $leaveRequest->delete();
        $workflow->delete();
    }
});
