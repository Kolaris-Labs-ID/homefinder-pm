<?php

namespace App\Policies\Hris;

use App\Models\User;
use App\Models\Hris\JobPosting;
use Illuminate\Auth\Access\HandlesAuthorization;

class JobPostingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * HR admin and HR staff can view job postings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_job::posting')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('view_job::posting')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can create models.
     * Only hr-admin can create job postings.
     */
    public function create(User $user): bool
    {
        return $user->can('create_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can update the model.
     * Only hr-admin can edit job postings.
     */
    public function update(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('update_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can delete the model.
     * Only hr-admin can delete job postings.
     */
    public function delete(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('delete_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('force_delete_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('restore_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, JobPosting $jobPosting): bool
    {
        return $user->can('replicate_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_job::posting')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can publish a job posting.
     * Only hr-admin can publish.
     */
    public function publish(User $user, JobPosting $jobPosting): bool
    {
        return $user->hasRole('hr-admin') && $jobPosting->isDraft();
    }

    /**
     * Determine whether the user can close a job posting.
     * Only hr-admin can close.
     */
    public function close(User $user, JobPosting $jobPosting): bool
    {
        return $user->hasRole('hr-admin') && $jobPosting->isPublished();
    }
}
