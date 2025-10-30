<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TicketComment;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ticket::comment');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TicketComment $ticketComment): bool
    {
        // Load ticket relation only if not already loaded to avoid N+1 queries
        if (!$ticketComment->relationLoaded('ticket')) {
            $ticketComment->load('ticket');
        }

        // If user can view the ticket, they can view the comments
        return $user->can('view', $ticketComment->ticket);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_ticket::comment');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TicketComment $ticketComment): bool
    {
        // Comment author or super admin can edit
        return $ticketComment->user_id === $user->id || $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TicketComment $ticketComment): bool
    {
        // Comment author or super admin can delete
        return $ticketComment->user_id === $user->id || $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ticket::comment');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, TicketComment $ticketComment): bool
    {
        return $user->can('force_delete_ticket::comment');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ticket::comment');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, TicketComment $ticketComment): bool
    {
        return $user->can('restore_ticket::comment');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ticket::comment');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, TicketComment $ticketComment): bool
    {
        return $user->can('replicate_ticket::comment');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_ticket::comment');
    }
}