<?php

namespace App\Policies\Hris;

use App\Models\Hris\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isHr($user);
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->id === $employee->user_id) {
            return true;
        }

        if ($this->isHr($user)) {
            return true;
        }

        $manager = optional($employee->primaryPosition)->manager;

        return $manager && $manager->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isHr($user);
    }

    public function update(User $user, Employee $employee): bool
    {
        if ($user->id === $employee->user_id) {
            return true;
        }

        return $this->isHr($user);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->isHr($user);
    }

    public function viewSensitive(User $user): bool
    {
        return $this->isHr($user);
    }

    protected function isHr(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff', 'super_admin']);
    }
}

