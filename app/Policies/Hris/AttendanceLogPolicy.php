<?php

namespace App\Policies\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\User;

class AttendanceLogPolicy
{
    /**
     * Determine whether the user can view any attendance logs.
     *
     * HR admin can view all, managers can view team attendance.
     */
    public function viewAny(User $user): bool
    {
        // HR admin can view all
        if ($this->isHr($user)) {
            return true;
        }

        // Managers can view team attendance
        if ($this->isManager($user)) {
            return true;
        }

        // Employees can view their own (will be filtered in view method)
        return (bool) $user->employee;
    }

    /**
     * Determine whether the user can view the attendance log.
     *
     * HR admin can view all, employee can view own, manager can view team.
     */
    public function view(User $user, AttendanceLog $attendanceLog): bool
    {
        // HR admin can view all
        if ($this->isHr($user)) {
            return true;
        }

        // Employee can view their own attendance
        if ($user->employee && $attendanceLog->employee_id === $user->employee->id) {
            return true;
        }

        // Manager can view team attendance
        return $this->isManagerOf($user, $attendanceLog->employee);
    }

    /**
     * Determine whether the user can create attendance logs.
     *
     * Only HR admin can manually create attendance logs.
     * Employees check-in/out through the API.
     */
    public function create(User $user): bool
    {
        return $this->isHr($user);
    }

    /**
     * Determine whether the user can update the attendance log.
     *
     * Only HR admin can update attendance logs.
     */
    public function update(User $user, AttendanceLog $attendanceLog): bool
    {
        return $this->isHr($user);
    }

    /**
     * Determine whether the user can delete the attendance log.
     *
     * Only HR admin can delete attendance logs.
     */
    public function delete(User $user, AttendanceLog $attendanceLog): bool
    {
        return $this->isHr($user);
    }

    /**
     * Determine whether the user can export attendance logs.
     *
     * Only HR admin can export attendance data.
     */
    public function export(User $user): bool
    {
        return $this->isHr($user);
    }

    /**
     * Determine whether the user can view team attendance.
     *
     * Managers can view their direct reports' attendance.
     */
    public function viewTeam(User $user): bool
    {
        if ($this->isHr($user)) {
            return true;
        }

        return $this->isManager($user);
    }

    /**
     * Check if user is HR admin or staff.
     */
    protected function isHr(User $user): bool
    {
        return $user->hasAnyRole(['hr-admin', 'hr-staff', 'super_admin']);
    }

    /**
     * Check if user is a manager.
     */
    protected function isManager(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'head-division']);
    }

    /**
     * Check if user is the manager of the given employee.
     */
    protected function isManagerOf(User $user, Employee $employee): bool
    {
        $manager = optional($employee->primaryPosition)->manager;

        return $manager && $manager->user_id === $user->id;
    }
}
