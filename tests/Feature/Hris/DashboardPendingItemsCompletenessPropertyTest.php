<?php

/**
 * Feature: filament-approvals-integration, Property 8: Dashboard pending items completeness
 * Validates: Requirements 5.1
 *
 * For any approver accessing the approval dashboard, the displayed items
 * SHALL include all approval steps where they are the designated approver
 * and status is pending.
 */

use App\Filament\Pages\ApprovalDashboard;
use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('dashboard displays all pending approval steps for direct user assignment', function () {
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

        // Get pending approvals using the service (same as dashboard uses)
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);

        // Verify all created requests appear in pending approvals
        expect($pendingApprovals)->toHaveCount($requestCount);

        // Verify each created request has a corresponding pending step
        foreach ($createdRequests as $request) {
            $found = $pendingApprovals->first(function ($step) use ($request) {
                return $step->approvable_type === LeaveRequest::class
                    && $step->approvable_id === $request->id
                    && $step->status === ApprovalStep::STATUS_PENDING;
            });
            expect($found)->not->toBeNull();
        }

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

test('dashboard displays all pending approval steps for role-based assignment', function () {
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

        // Get pending approvals using the service
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);

        // Verify all created requests appear in pending approvals
        expect($pendingApprovals)->toHaveCount($requestCount);

        // Verify each created request has a corresponding pending step
        foreach ($createdRequests as $request) {
            $found = $pendingApprovals->first(function ($step) use ($request) {
                return $step->approvable_type === Reimbursement::class
                    && $step->approvable_id === $request->id
                    && $step->status === ApprovalStep::STATUS_PENDING;
            });
            expect($found)->not->toBeNull();
        }

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


test('dashboard displays pending items from multiple modules', function () {
    // Create roles
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);

    // Create approver user with role
    $approver = User::factory()->create();
    $approver->assignRole('hr-admin');

    $service = new ApprovalWorkflowService();
    $iterations = 10;

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
                ['order' => 1, 'type' => 'role', 'value' => 'hr-admin'],
            ],
            'is_active' => true,
        ]);

        // Create random number of items for each module
        $leaveCount = fake()->numberBetween(1, 3);
        $reimbursementCount = fake()->numberBetween(1, 3);

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

        // Get all pending approvals
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);

        // Total should be sum of both modules
        $expectedTotal = $leaveCount + $reimbursementCount;
        expect($pendingApprovals)->toHaveCount($expectedTotal);

        // Verify leave requests are included
        foreach ($leaveRequests as $request) {
            $found = $pendingApprovals->first(function ($step) use ($request) {
                return $step->approvable_type === LeaveRequest::class
                    && $step->approvable_id === $request->id;
            });
            expect($found)->not->toBeNull();
        }

        // Verify reimbursements are included
        foreach ($reimbursements as $request) {
            $found = $pendingApprovals->first(function ($step) use ($request) {
                return $step->approvable_type === Reimbursement::class
                    && $step->approvable_id === $request->id;
            });
            expect($found)->not->toBeNull();
        }

        // Clean up
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

test('dashboard excludes non-pending approval steps', function () {
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
                ['order' => 2, 'type' => 'user', 'value' => $approver->id],
            ],
            'is_active' => true,
        ]);

        // Create leave request and initialize workflow
        $leaveRequest = LeaveRequest::factory()->create([
            'status' => LeaveRequest::STATUS_PENDING,
        ]);
        $service->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

        // Get pending approvals - should only show step 1 (pending)
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);
        expect($pendingApprovals)->toHaveCount(1);
        expect($pendingApprovals->first()->step_order)->toBe(1);
        expect($pendingApprovals->first()->status)->toBe(ApprovalStep::STATUS_PENDING);

        // Approve step 1
        $step1 = $pendingApprovals->first();
        $service->approve($step1, $approver, 'Approved');

        // Now step 2 should be pending
        $pendingApprovals = $service->getPendingApprovalsForUser($approver);
        expect($pendingApprovals)->toHaveCount(1);
        expect($pendingApprovals->first()->step_order)->toBe(2);
        expect($pendingApprovals->first()->status)->toBe(ApprovalStep::STATUS_PENDING);

        // Clean up
        ApprovalStep::where('approvable_id', $leaveRequest->id)
            ->where('approvable_type', LeaveRequest::class)
            ->delete();
        $leaveRequest->delete();
        $workflow->delete();
    }
});
