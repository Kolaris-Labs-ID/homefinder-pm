<?php

namespace App\Policies\Hris;

use App\Models\Hris\ExpenseRequest;
use App\Models\User;

class ExpenseRequestPolicy
{
    public function view(User $user, ExpenseRequest $er): bool
    {
        return $this->isOwner($user, $er) || $this->hasPerm($user, 'expense_requests.view_all');
    }

    public function uploadEvidence(User $user, ExpenseRequest $er): bool
    {
        return $this->ownerCanEdit($user, $er);
    }

    public function submit(User $user, ExpenseRequest $er): bool
    {
        return $this->ownerCanEdit($user, $er);
    }

    public function update(User $user, ExpenseRequest $er): bool
    {
        return $this->ownerCanEdit($user, $er);
    }

    public function revise(User $user, ExpenseRequest $er): bool
    {
        return $this->isOwner($user, $er) && $er->status === ExpenseRequest::STATUS_NEEDS_REVISION;
    }

    private function ownerCanEdit(User $user, ExpenseRequest $er): bool
    {
        return $this->isOwner($user, $er) && in_array($er->status, [
            ExpenseRequest::STATUS_DRAFT,
            ExpenseRequest::STATUS_NEEDS_REVISION,
        ], true);
    }

    private function isOwner(User $user, ExpenseRequest $er): bool
    {
        return $user->employee && (int) $er->employee_id === (int) $user->employee->id;
    }

    private function hasPerm(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }
}
