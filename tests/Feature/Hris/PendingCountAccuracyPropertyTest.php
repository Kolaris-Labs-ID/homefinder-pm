<?php

/**
 * Feature: filament-approvals-integration, Property 6: Pending count accuracy
 * Validates: Requirements 3.2
 *
 * For any user with approver privileges, the navigation badge count
 * SHALL equal the count of approval steps where that user is the
 * designated approver and status is pending.
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

test('pending count equals actual pending approval steps for direct user assignment', function () {
    // Create approver user
    $approver = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 10;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with direct user assignment
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_LEAVE,
            'steps' => [
                ['order' => 1, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create random number of leave requests
        $requestCount = fake()->numberBetween(1, 5);
        $createdRequests = [];

        for ($j = 0; $j < $requestCount; $j++) {
            $leaveRequest = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
            $createdRequests[] = $leaveRequest;
        }

        // Get statistics (which includes pending count)
        $stats = $service->getApprovalStatistics($approver);

        // Verify pending count matches actual count
        expect($stats['pending'])->toBe($requestCount);

        // Also verify by directly counting pending steps
        $actualPendingCount = ApprovalStep::where('status', ApprovalStep::STATUS_PENDING)
            ->where('approver_id', $approver->id)
            ->count();
        expect($stats['pending'])->toBe($actualPendingCount);

        // Clean up for next iteration
        ApprovalStep::whereIn('approvable_id', collect($createdRequests)->pluck('id'))
            ->where('approvable_type', LeaveRequest::class)
            ->delete();
        foreach ($createdRequests as $request) {
            $request->delete();
        }
        $workflow->delete();
    }
});

test('pending count equals actual pending approval steps for role-based assignment', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver user with role
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 10;

    for ($i = 0; $i < $iterations; $i++) {
        // Create workflow with role-based assignment
        $workflow = ApprovalWorkflow::create([
            'name' => 'Test Workflow ' . $i,
            'for_module' => ApprovalWorkflow::MODULE_REIMBURSEMENT,
            'steps' => [
                ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
            ],
            'is_active' => true,
        ]);

        // Create random number of reimbursements
        $requestCount = fake()->numberBetween(1, 5);
        $createdRequests = [];

        for ($j = 0; $j < $requestCount; $j++) {
            $reimbursement = Reimbursement::factory()->create([
                'status' => Reimbursement::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($reimbursement, ApprovalWorkflow::MODULE_REIMBURSEMENT);
            $createdRequests[] = $reimbursement;
        }

        // Get statistics
        $stats = $service->getApprovalStatistics($approver);

        // Verify pending count matches actual count
        expect($stats['pending'])->toBe($requestCount);

        // Also verify by directly counting pending steps with role
        $actualPendingCount = ApprovalStep::where('status', ApprovalStep::STATUS_PENDING)
            ->where('approver_role', 'hr-admin')
            ->count();
        expect($stats['pending'])->toBe($actualPendingCount);

        // Clean up for next iteration
        ApprovalStep::whereIn('approvable_id', collect($createdRequests)->pluck('id'))
            ->where('approvable_type', Reimbursement::class)
            ->delete();
        foreach ($createdRequests as $request) {
            $request->delete();
        }
        $workflow->delete();
    }
});

test('pending count updates correctly after approval actions', function () {
    // Create approver user
    $approver = User::factory()->create();

    $service = new ApprovalWorkflowService();
    $iterations = 10;

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

        // Create 3 leave requests
        $requests = [];
        for ($j = 0; $j < 3; $j++) {
            $leaveRequest = LeaveRequest::factory()->create([
                'status' => LeaveRequest::STATUS_PENDING,
            ]);
            $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);
            $requests[] = $leaveRequest;
        }

        // Initial count should be 3
        $stats = $service->getApprovalStatistics($approver);
        expect($stats['pending'])->toBe(3);

        // Approve one
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);
        $service->approve($pendingApprovals->first(), $approver, 'Approved');

        // Count should now be 2
        $stats = $service->getApprovalStatistics($approver);
        expect($stats['pending'])->toBe(2);

        // Reject one
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);
        $service->reject($pendingApprovals->first(), $approver, 'Rejected');

        // Count should now be 1
        $stats = $service->getApprovalStatistics($approver);
        expect($stats['pending'])->toBe(1);

        // Clean up
        ApprovalStep::whereIn('approvable_id', collect($requests)->pluck('id'))
            ->where('approvable_type', LeaveRequest::class)
            ->delete();
        foreach ($requests as $request) {
            $request->delete();
        }
        $workflow->delete();
    }
});
