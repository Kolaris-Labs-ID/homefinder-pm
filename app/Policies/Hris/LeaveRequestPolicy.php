<?php

namespace App\Policies\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isHr($user) || $this->isManager($user);
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee->user_id === $user->id) {
            return true;
        }

        if ($leaveRequest->current_approver_id === $user->id) {
            return true;
        }

        if ($this->isHr($user)) {
            return true;
        }

        return $this->isManagerOf($user, $leaveRequest->employee);
    }

    public function create(User $user): bool
    {
        return (bool) $user->employee;
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        if (in_array($leaveRequest->status, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED], true)) {
            return false;
        }

        return $leaveRequest->employee->user_id === $user->id;
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING], true)) {
            return false;
        }

        if ($leaveRequest->current_approver_id === $user->id) {
            return true;
        }

        return $this->isHr($user);
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->employee->user_id !== $user->id) {
            return false;
        }

        return in_array($leaveRequest->status, [
            LeaveRequest::STATUS_DRAFT,
            LeaveRequest::STATUS_PENDING,
            LeaveRequest::STATUS_APPROVED,
        ], true);
    }

    protected function isHr(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff', 'super_admin']);
    }

    protected function isManager(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'head-division']);
    }

    protected function isManagerOf(User $user, Employee $employee): bool
    {
        $manager = optional($employee->primaryPosition)->manager;

        return $manager && $manager->user_id === $user->id;
    }
}

