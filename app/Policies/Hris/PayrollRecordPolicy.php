<?php

namespace App\Policies\Hris;

use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_payroll::record') || $this->isFinanceApprover($user);
    }

    public function view(User $user, PayrollRecord $payrollRecord): bool
    {
        // Employee can view their own payroll records
        if ($user->employee && $payrollRecord->employee_id === $user->employee->id) {
            return true;
        }

        return $user->can('view_payroll::record') || $this->isFinanceApprover($user);
    }

    public function create(User $user): bool
    {
        return $user->can('create_payroll::record');
    }

    public function update(User $user, PayrollRecord $payrollRecord): bool
    {
        return $user->can('update_payroll::record') && $payrollRecord->isEditable();
    }

    public function delete(User $user, PayrollRecord $payrollRecord): bool
    {
        return $user->can('delete_payroll::record') && $payrollRecord->isEditable();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_payroll::record');
    }

    public function forceDelete(User $user, PayrollRecord $payrollRecord): bool
    {
        return $user->can('force_delete_payroll::record');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_payroll::record');
    }

    public function restore(User $user, PayrollRecord $payrollRecord): bool
    {
        return $user->can('restore_payroll::record');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_payroll::record');
    }

    public function replicate(User $user, PayrollRecord $payrollRecord): bool
    {
        return $user->can('replicate_payroll::record');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_payroll::record');
    }

    /**
     * Check if the user is a configured finance approver in any active payroll workflow.
     */
    protected function isFinanceApprover(User $user): bool
    {
        $modules = [PayrollWorkflowConfig::MODULE_MONTHLY, PayrollWorkflowConfig::MODULE_WEEKLY];

        foreach ($modules as $module) {
            $config = PayrollWorkflowConfig::getActiveConfig($module);
            if (!$config) {
                continue;
            }

            $isApprover = $config->steps()
                ->where('step_type', PayrollWorkflowStep::STEP_FINANCE)
                ->get()
                ->contains(fn ($step) => $step->canBeActedOnBy($user));

            if ($isApprover) {
                return true;
            }
        }

        return false;
    }
}

