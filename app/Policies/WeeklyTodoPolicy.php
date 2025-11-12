<?php

namespace App\Policies;

use App\Models\WeeklyTodo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class WeeklyTodoPolicy
{
    /**
     * Check if user is in full management role (can view all todos across all divisions)
     */
    private function isFullManagementRole(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin', 'CEO', 'COO', 'CTO', 'HR']);
    }

    /**
     * Check if user is head division (can view their division's todos)
     */
    private function isHeadDivision(User $user): bool
    {
        return $user->is_division_head && $user->division;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Full management roles can view all
        if ($this->isFullManagementRole($user)) {
            return true;
        }
        
        // Head Division can view their division's todos
        if ($this->isHeadDivision($user)) {
            return true;
        }
        
        // Regular users can view their own todos (through resource query)
        return $user->can('view_any_weekly_todo') || $user->can('view_weekly::todo');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WeeklyTodo $weeklyTodo): bool
    {
        // Full management roles can view all
        if ($this->isFullManagementRole($user)) {
            return true;
        }
        
        // Head Division can view todos in their division
        if ($this->isHeadDivision($user)) {
            // Load user relationship if not already loaded
            if (!$weeklyTodo->relationLoaded('user')) {
                $weeklyTodo->load('user');
            }
            return $weeklyTodo->user && $weeklyTodo->user->division === $user->division;
        }
        
        // Users can view their own todos
        return $weeklyTodo->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create their own todos
        // Check for create permission (supports both formats)
        return $user->can('create_weekly::todo') 
            || $user->can('create_weekly_todo');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WeeklyTodo $weeklyTodo): bool
    {
        // Full management roles can update all
        if ($this->isFullManagementRole($user)) {
            return true;
        }
        
        // Head Division can update todos in their division
        if ($this->isHeadDivision($user)) {
            // Load user relationship if not already loaded
            if (!$weeklyTodo->relationLoaded('user')) {
                $weeklyTodo->load('user');
            }
            return $weeklyTodo->user && $weeklyTodo->user->division === $user->division;
        }
        
        // Users can only update their own todos
        return $weeklyTodo->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WeeklyTodo $weeklyTodo): bool
    {
        // Full management roles can delete all
        if ($this->isFullManagementRole($user)) {
            return true;
        }
        
        // Head Division can delete todos in their division
        if ($this->isHeadDivision($user)) {
            // Load user relationship if not already loaded
            if (!$weeklyTodo->relationLoaded('user')) {
                $weeklyTodo->load('user');
            }
            return $weeklyTodo->user && $weeklyTodo->user->division === $user->division;
        }
        
        // Users can only delete their own todos
        return $weeklyTodo->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WeeklyTodo $weeklyTodo): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WeeklyTodo $weeklyTodo): bool
    {
        return false;
    }
}

