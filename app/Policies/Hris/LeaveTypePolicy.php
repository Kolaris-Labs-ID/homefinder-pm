<?php

namespace App\Policies\Hris;

use App\Models\Hris\LeaveType;
use App\Models\User;

class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function create(User $user): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->isHrOrExecutive($user);
    }

    protected function isHrOrExecutive(User $user): bool
    {
        return $this->isHr($user) || $this->isExecutive($user);
    }

    protected function isHr(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff', 'super_admin']);
    }

    protected function isExecutive(User $user): bool
    {
        return $user->hasAnyRole(['CEO', 'COO']);
    }
}

