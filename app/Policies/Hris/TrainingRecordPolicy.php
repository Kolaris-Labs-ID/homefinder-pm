<?php

namespace App\Policies\Hris;

use App\Models\Hris\TrainingRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TrainingRecordPolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user owns the training record (is the employee).
     */
    protected function isOwnRecord(User $user, TrainingRecord $trainingRecord): bool
    {
        $employee = $user->employee;

        return $employee && $trainingRecord->employee_id === $employee->id;
    }

    /**
     * Determine whether the user can view any models.
     * HR admin can view all, employees can view their own.
     */
    public function viewAny(User $user): bool
    {
        // HR admin and HR staff can view all
        if ($user->can('view_any_training::record') || $user->hasAnyRole(['hr-admin', 'hr-staff'])) {
            return true;
        }

        // Employees can view their own (they'll see filtered results)
        return $user->employee !== null;
    }

    /**
     * Determine whether the user can view the model.
     * Employees can view their own records, hr-admin can view all.
     */
    public function view(User $user, TrainingRecord $trainingRecord): bool
    {
        if ($user->can('view_training::record')) {
            return true;
        }

        // HR admin and HR staff can view all
        if ($user->hasAnyRole(['hr-admin', 'hr-staff'])) {
            return true;
        }

        // Employees can view their own records
        return $this->isOwnRecord($user, $trainingRecord);
    }

    /**
     * Determine whether the user can create models.
     * HR permissions or hr-admin role; employees with an HRIS profile may submit their own training.
     */
    public function create(User $user): bool
    {
        return $user->can('create_training::record')
            || $user->hasRole('hr-admin')
            || $user->employee !== null;
    }

    /**
     * Determine whether the user can update the model.
     * Only hr-admin can update training records.
     */
    public function update(User $user, TrainingRecord $trainingRecord): bool
    {
        return $user->can('update_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can delete the model.
     * Only hr-admin can delete training records.
     */
    public function delete(User $user, TrainingRecord $trainingRecord): bool
    {
        return $user->can('delete_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, TrainingRecord $trainingRecord): bool
    {
        return $user->can('force_delete_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, TrainingRecord $trainingRecord): bool
    {
        return $user->can('restore_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, TrainingRecord $trainingRecord): bool
    {
        return $user->can('replicate_training::record')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_training::record')
            || $user->hasRole('hr-admin');
    }
}
