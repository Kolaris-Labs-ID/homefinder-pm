<?php

namespace App\Helpers;

use App\Models\User;

class RoleHelper
{
    /**
     * C-Level roles that can only assign to Head Division
     */
    private static array $cLevelRoles = ['CEO', 'COO', 'CTO', 'HR'];

    /**
     * Head Division role name
     */
    private static string $headDivisionRole = 'Head Division';

    /**
     * Check if user has a C-Level role (CEO, COO, CTO, HR)
     */
    public static function isCLevel(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(self::$cLevelRoles);
    }

    /**
     * Check if user is Head Division
     */
    public static function isHeadDivision(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        
        if (!$user) {
            return false;
        }

        return $user->hasRole(self::$headDivisionRole);
    }

    /**
     * Check if user can assign to the target user based on role hierarchy
     * 
     * @param User $targetUser The user being assigned to
     * @param User|null $assigner The user doing the assignment (defaults to current user)
     * @return bool
     */
    public static function canAssignTo(User $targetUser, ?User $assigner = null): bool
    {
        $assigner = $assigner ?? auth()->user();
        
        if (!$assigner) {
            return false;
        }

        // Head Division can assign to anyone
        if (self::isHeadDivision($assigner)) {
            return true;
        }

        // C-Level roles can only assign to Head Division
        if (self::isCLevel($assigner)) {
            return self::isHeadDivision($targetUser);
        }

        // Other roles (no restrictions for now, but can be added later)
        return true;
    }

    /**
     * Get users that can be assigned based on current user's role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query Base query for users
     * @param User|null $assigner The user doing the assignment (defaults to current user)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function scopeAllowedAssignees($query, ?User $assigner = null)
    {
        $assigner = $assigner ?? auth()->user();
        
        if (!$assigner) {
            return $query->whereRaw('1 = 0'); // No users if no assigner
        }

        // Head Division can assign to anyone - no additional restrictions
        if (self::isHeadDivision($assigner)) {
            return $query; // No restrictions
        }

        // C-Level roles can only assign to Head Division
        if (self::isCLevel($assigner)) {
            return $query->role(self::$headDivisionRole);
        }

        // Other roles - no restrictions for now
        return $query;
    }

    /**
     * Validate if assignment is allowed
     * 
     * @param array $userIds Array of user IDs to assign
     * @param User|null $assigner The user doing the assignment (defaults to current user)
     * @return array ['allowed' => [...], 'disallowed' => [...]]
     */
    public static function validateAssignments(array $userIds, ?User $assigner = null): array
    {
        $assigner = $assigner ?? auth()->user();
        
        if (!$assigner) {
            return ['allowed' => [], 'disallowed' => $userIds];
        }

        $allowed = [];
        $disallowed = [];

        foreach ($userIds as $userId) {
            $targetUser = User::find($userId);
            
            if (!$targetUser) {
                $disallowed[] = $userId;
                continue;
            }

            if (self::canAssignTo($targetUser, $assigner)) {
                $allowed[] = $userId;
            } else {
                $disallowed[] = $userId;
            }
        }

        return ['allowed' => $allowed, 'disallowed' => $disallowed];
    }
}

