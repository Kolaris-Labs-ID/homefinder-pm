<?php

namespace App\Policies\Hris;

use App\Models\Hris\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function create(User $user): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function update(User $user, Department $department): bool
    {
        return $this->isHrOrExecutive($user);
    }

    public function delete(User $user, Department $department): bool
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

