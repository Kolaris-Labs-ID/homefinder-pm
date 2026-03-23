<?php

namespace App\Services\Hris;

use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\User;

class LeaveWorkflowService
{
    protected ApprovalWorkflowService $approvalWorkflowService;

    public function __construct(ApprovalWorkflowService $approvalWorkflowService)
    {
        $this->approvalWorkflowService = $approvalWorkflowService;
    }

    /**
     * Initialize workflow for a leave request.
     * Uses ApprovalWorkflowService if an active workflow exists for the leave module,
     * otherwise falls back to simple approver assignment.
     */
    public function initializeWorkflow(LeaveRequest $leaveRequest): void
    {
        // Update leave request to pending status
        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        // Check if there's an active approval workflow for leave module
        $hasActiveWorkflow = ApprovalWorkflow::where('for_module', ApprovalWorkflow::MODULE_LEAVE)
            ->where('is_active', true)
            ->exists();

        if ($hasActiveWorkflow) {
            // Use parallel workflow: all approvers see the request simultaneously
            // Any single approval is sufficient to approve the leave request
            $this->approvalWorkflowService->initializeParallelWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

            // Set current_approver_id to null since any approver can act
            $leaveRequest->update([
                'current_approver_id' => null,
            ]);
        } else {
            // Fall back to simple approver assignment
            // Always create an ApprovalStep so it appears in the Approval Dashboard
            $approver = $this->determineDefaultApprover($leaveRequest->employee);

            if ($approver) {
                $leaveRequest->update([
                    'current_approver_id' => $approver->id,
                ]);
            }

            // Create an ApprovalStep record for the dashboard
            // Use specific approver if found, otherwise use role-based assignment
            \App\Models\Hris\ApprovalStep::create([
                'workflow_id' => null,
                'workflow_snapshot' => [
                    'workflow_name' => 'Default Leave Approval',
                    'for_module' => 'leave',
                    'steps' => [['order' => 1, 'type' => $approver ? 'user' : 'role', 'value' => $approver ? $approver->id : 'super_admin']],
                    'snapshot_at' => now()->toIso8601String(),
                ],
                'approvable_type' => LeaveRequest::class,
                'approvable_id' => $leaveRequest->id,
                'step_order' => 1,
                'approver_id' => $approver?->id,
                'approver_role' => $approver ? null : 'super_admin',
                'status' => \App\Models\Hris\ApprovalStep::STATUS_PENDING,
                'approved_at' => null,
                'notes' => null,
            ]);
        }
    }

    /**
     * Determine the default approver for an employee when no workflow is configured.
     */
    public function determineDefaultApprover(Employee $employee): ?User
    {
        // First, try to get the employee's manager
        $managerUser = optional($employee->primaryPosition)->manager?->user;

        if ($managerUser && $managerUser->id !== $employee->user_id) {
            return $managerUser;
        }

        // Fall back to HR admin
        $hrAdmin = User::whereHas('roles', fn ($query) => $query->where('name', 'hr-admin'))->first();
        if ($hrAdmin) {
            return $hrAdmin;
        }

        // Fall back to HR staff
        return User::whereHas('roles', fn ($query) => $query->where('name', 'hr-staff'))->first();
    }
}

