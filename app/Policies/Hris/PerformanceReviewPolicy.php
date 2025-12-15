<?php

namespace App\Policies\Hris;

use App\Models\User;
use App\Models\Hris\PerformanceReview;
use Illuminate\Auth\Access\HandlesAuthorization;

class PerformanceReviewPolicy
{
    use HandlesAuthorization;

    /**
     * Check if the user is the employee being reviewed.
     */
    protected function isOwnReview(User $user, PerformanceReview $review): bool
    {
        $employee = $user->employee;
        return $employee && $review->employee_id === $employee->id;
    }

    /**
     * Check if the user is the reviewer.
     */
    protected function isReviewer(User $user, PerformanceReview $review): bool
    {
        return $review->reviewer_id === $user->id;
    }

    /**
     * Check if the user is a manager who can manage this review.
     */
    protected function isManagerOfEmployee(User $user, PerformanceReview $review): bool
    {
        // HR admin and HR manager can manage all reviews
        if ($user->hasAnyRole(['hr-admin', 'hr-manager'])) {
            return true;
        }

        // Check if user is the reviewer
        if ($this->isReviewer($user, $review)) {
            return true;
        }

        // Check if user has an employee record and is in the same department with manager role
        $employee = $review->employee;
        if (!$employee) {
            return false;
        }

        $userEmployee = $user->employee;
        if ($userEmployee) {
            $userPosition = $userEmployee->primaryPosition;
            $employeePosition = $employee->primaryPosition;

            if ($userPosition && $employeePosition) {
                if ($userPosition->department_id === $employeePosition->department_id) {
                    return $user->hasRole('manager');
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager', 'manager']);
    }

    /**
     * Determine whether the user can view the model.
     * Employees can view their own reviews only.
     */
    public function view(User $user, PerformanceReview $review): bool
    {
        if ($user->can('view_performance::review')) {
            return true;
        }

        // Employees can view their own reviews
        if ($this->isOwnReview($user, $review)) {
            return true;
        }

        // Reviewers can view reviews they created
        if ($this->isReviewer($user, $review)) {
            return true;
        }

        // HR admin and HR manager can view all
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can create models.
     * Only HR admin, HR manager, and managers can create reviews.
     */
    public function create(User $user): bool
    {
        return $user->can('create_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager', 'manager']);
    }

    /**
     * Determine whether the user can update the model.
     * Only the reviewer or HR admin can update.
     */
    public function update(User $user, PerformanceReview $review): bool
    {
        if ($user->can('update_performance::review')) {
            return true;
        }

        // Check if cycle is closed - no updates allowed
        if ($review->cycle && $review->cycle->status === 'closed') {
            return false;
        }

        // Check if already submitted - no updates allowed
        if ($review->isSubmitted()) {
            return $user->hasRole('hr-admin'); // Only HR admin can update submitted reviews
        }

        // Reviewer can update their own review
        if ($this->isReviewer($user, $review)) {
            return true;
        }

        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PerformanceReview $review): bool
    {
        if ($user->can('delete_performance::review')) {
            return true;
        }

        // Check if cycle is closed - no deletes allowed
        if ($review->cycle && $review->cycle->status === 'closed') {
            return false;
        }

        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PerformanceReview $review): bool
    {
        return $user->can('force_delete_performance::review')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_performance::review')
            || $user->hasRole('hr-admin');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, PerformanceReview $review): bool
    {
        return $user->can('restore_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, PerformanceReview $review): bool
    {
        return $user->can('replicate_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_performance::review')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can submit a review.
     */
    public function submit(User $user, PerformanceReview $review): bool
    {
        // Check if cycle is closed - no submissions allowed
        if ($review->cycle && $review->cycle->status === 'closed') {
            return false;
        }

        // Already submitted
        if ($review->isSubmitted()) {
            return false;
        }

        // Reviewer can submit their own review
        if ($this->isReviewer($user, $review)) {
            return true;
        }

        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }
}
