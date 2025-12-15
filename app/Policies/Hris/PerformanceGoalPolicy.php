<?php

namespace App\Policies\Hris;

use App\Models\User;
use App\Models\Hris\PerformanceGoal;
use Illuminate\Auth\Access\HandlesAuthorization;

class PerformanceGoalPolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user is a manager of the employee associated with the goal.
     * A manager is determined by having the hr-manager role or being in a supervisory position.
     */
    protected function isManagerOfEmployee(User $user, PerformanceGoal $goal): bool
    {
        // HR admin and HR manager can manage all goals
        if ($user->hasAnyRole(['hr-admin', 'hr-manager'])) {
            return true;
        }

        // Check if user has an employee record and is the reviewer for this employee
        $employee = $goal->employee;
        if (!$employee) {
            return false;
        }

        // Check if the user is in the same department and has manager role
        $userEmployee = $user->employee;
        if ($userEmployee) {
            $userPosition = $userEmployee->primaryPosition;
            $employeePosition = $employee->primaryPosition;

            if ($userPosition && $employeePosition) {
                // Same department and user has a managerial position
                if ($userPosition->department_id === $employeePosition->department_id) {
                    return $user->hasRole('manager');
                }
            }
        }

        return false;
    }

    /**
     * Check if the user owns the goal (is the employee).
     */
    protected function isOwnGoal(User $user, PerformanceGoal $goal): bool
    {
        $employee = $user->employee;
        return $employee && $goal->employee_id === $employee->id;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager', 'manager']);
    }

    /**
     * Determine whether the user can view the model.
     * Managers can view goals for their direct reports.
     * Employees can view their own goals.
     */
    public function view(User $user, PerformanceGoal $goal): bool
    {
        if ($user->can('view_performance::goal')) {
            return true;
        }

        // Employees can view their own goals
        if ($this->isOwnGoal($user, $goal)) {
            return true;
        }

        // Managers can view goals for their direct reports
        return $this->isManagerOfEmployee($user, $goal);
    }

    /**
     * Determine whether the user can create models.
     * Only HR admin, HR manager, and managers can create goals.
     */
    public function create(User $user): bool
    {
        return $user->can('create_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager', 'manager']);
    }

    /**
     * Determine whether the user can update the model.
     * Managers can edit goals for their direct reports.
     */
    public function update(User $user, PerformanceGoal $goal): bool
    {
        if ($user->can('update_performance::goal')) {
            return true;
        }

        // Check if cycle is closed - no updates allowed
        if ($goal->cycle && $goal->cycle->status === 'closed') {
            return false;
        }

        // Managers can edit goals for their direct reports
        return $this->isManagerOfEmployee($user, $goal);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PerformanceGoal $goal): bool
    {
        if ($user->can('delete_performance::goal')) {
            return true;
        }

        // Check if cycle is closed - no deletes allowed
        if ($goal->cycle && $goal->cycle->status === 'closed') {
            return false;
        }

        return $this->isManagerOfEmployee($user, $goal);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('force_delete_performance::goal')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_performance::goal')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('restore_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('replicate_performance::goal')
            || $this->isManagerOfEmployee($user, $goal);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_performance::goal')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can update progress on a goal.
     * Managers can update progress for their direct reports.
     */
    public function updateProgress(User $user, PerformanceGoal $goal): bool
    {
        // Check if cycle is closed - no updates allowed
        if ($goal->cycle && $goal->cycle->status === 'closed') {
            return false;
        }

        return $this->isManagerOfEmployee($user, $goal);
    }
}
