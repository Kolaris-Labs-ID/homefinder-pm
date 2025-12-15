<?php

namespace App\Policies\Hris;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class HRAnalyticsPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can access HR analytics.
     * Restricted to hr-admin and hr-manager roles.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->can('view_hr_analytics')
            || $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view headcount reports.
     */
    public function viewHeadcount(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view turnover reports.
     */
    public function viewTurnover(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view age distribution.
     */
    public function viewAgeDistribution(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view tenure distribution.
     */
    public function viewTenureDistribution(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view leave usage summary.
     */
    public function viewLeaveUsage(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view attendance KPIs.
     */
    public function viewAttendanceKPIs(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can view recruitment metrics.
     */
    public function viewRecruitmentMetrics(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can export HR data.
     */
    public function exportData(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }

    /**
     * Determine whether the user can access the HR dashboard page.
     */
    public function accessDashboard(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-manager']);
    }
}
