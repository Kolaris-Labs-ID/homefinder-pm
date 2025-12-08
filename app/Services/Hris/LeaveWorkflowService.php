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
            // Use the approval workflow service to create approval steps
            $this->approvalWorkflowService->initializeWorkflow($leaveRequest, ApprovalWorkflow::MODULE_LEAVE);

            // Update current_approver_id from the first pending step
            $currentStep = $this->approvalWorkflowService->getCurrentStep($leaveRequest);
            if ($currentStep && $currentStep->approver_id) {
                $leaveRequest->update([
                    'current_approver_id' => $currentStep->approver_id,
                ]);
            }
        } else {
            // Fall back to simple approver assignment
            $approver = $this->determineDefaultApprover($leaveRequest->employee);

            if ($approver) {
                $leaveRequest->update([
                    'current_approver_id' => $approver->id,
                ]);
            }
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

