<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ticket');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Super admin can view all tickets
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Check if user is assigned to the ticket
        if ($ticket->assignees()->where('users.id', $user->id)->exists()) {
            return true;
        }

        // Check if user created the ticket
        if ($ticket->created_by === $user->id) {
            return true;
        }

        // Check if user is a member of the project
        if ($ticket->project && $ticket->project->members()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_ticket');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Super admin can update all tickets
        if ($user->hasRole(['super_admin'])) {
            return true;
        }

        // Check if user created the ticket
        if ($ticket->created_by === $user->id) {
            return true;
        }

        // Check if user is assigned to the ticket
        if ($ticket->assignees()->where('users.id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('delete_ticket');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ticket');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $user->can('force_delete_ticket');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ticket');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->can('restore_ticket');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ticket');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Ticket $ticket): bool
    {
        return $user->can('replicate_ticket');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_ticket');
    }
}
