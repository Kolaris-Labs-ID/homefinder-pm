<?php

namespace App\Policies\Hris;

use App\Models\Hris\Applicant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApplicantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can view the model.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function view(User $user, Applicant $applicant): bool
    {
        return $user->can('view_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can create models.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function create(User $user): bool
    {
        return $user->can('create_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can update the model.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function update(User $user, Applicant $applicant): bool
    {
        return $user->can('update_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can delete the model.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function delete(User $user, Applicant $applicant): bool
    {
        return $user->can('delete_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Applicant $applicant): bool
    {
        return $user->can('force_delete_applicant')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_applicant')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Applicant $applicant): bool
    {
        return $user->can('restore_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Applicant $applicant): bool
    {
        return $user->can('replicate_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can view (stream/preview) an applicant's résumé.
     */
    public function viewResume(User $user, Applicant $applicant): bool
    {
        return $user->can('view_resume_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can download an applicant's résumé.
     */
    public function downloadResume(User $user, Applicant $applicant): bool
    {
        return $user->can('download_resume_applicant')
            || $user->hasAnyRole(['hr-admin', 'hr-staff']);
    }

    /**
     * Determine whether the user can advance an applicant in the pipeline.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function advance(User $user, Applicant $applicant): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff']) && $applicant->canAdvance();
    }

    /**
     * Determine whether the user can reject an applicant.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function reject(User $user, Applicant $applicant): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff']) && ! $applicant->isRejected() && ! $applicant->isHired();
    }

    /**
     * Determine whether the user can schedule an interview for an applicant.
     * Restricted to hr-admin and hr-staff roles.
     */
    public function scheduleInterview(User $user, Applicant $applicant): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff']) && ! $applicant->isRejected() && ! $applicant->isHired();
    }

    /**
     * Screening abilities check the explicit recruitment.* permission ONLY — no role
     * fallback — so applicant view/manage access never implies screening access.
     */
    public function viewScreening(User $user, ?Applicant $applicant = null): bool
    {
        return $user->can('recruitment.view_screening');
    }

    public function runScreening(User $user, ?Applicant $applicant = null): bool
    {
        return $user->can('recruitment.run_screening');
    }

    public function overrideScreening(User $user, ?Applicant $applicant = null): bool
    {
        return $user->can('recruitment.override_screening');
    }

    public function clearScreeningOverride(User $user, ?Applicant $applicant = null): bool
    {
        return $user->can('recruitment.clear_screening_override');
    }
}
