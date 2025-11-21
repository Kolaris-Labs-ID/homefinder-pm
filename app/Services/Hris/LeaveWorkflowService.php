<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\User;

class LeaveWorkflowService
{
    public function initializeWorkflow(LeaveRequest $leaveRequest): void
    {
        $approver = $this->determineDefaultApprover($leaveRequest->employee);

        if ($approver) {
            $leaveRequest->update([
                'current_approver_id' => $approver->id,
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted_at' => now(),
            ]);
        } else {
            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted_at' => now(),
            ]);
        }
    }

    public function determineDefaultApprover(Employee $employee): ?User
    {
        $managerUser = optional($employee->primaryPosition)->manager?->user;

        if ($managerUser && $managerUser->id !== $employee->user_id) {
            return $managerUser;
        }

        $hrAdmin = User::whereHas('roles', fn ($query) => $query->where('name', 'hr-admin'))->first();
        if ($hrAdmin) {
            return $hrAdmin;
        }

        return User::whereHas('roles', fn ($query) => $query->where('name', 'hr-staff'))->first();
    }
}

