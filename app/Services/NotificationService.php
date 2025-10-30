<?php

namespace App\Services;

use App\Mail\ProjectAssignmentNotification;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyCommentAdded(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        $commenter = $comment->user;
        
        $usersToNotify = $this->getUsersToNotifyForComment($ticket, $commenter);
        
        foreach ($usersToNotify as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'comment_added',
                'title' => 'New Comment on Ticket',
                'message' => "{$commenter->name} added a comment on ticket {$ticket->title}",
                'data' => [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'commenter_id' => $commenter->id,
                    'commenter_name' => $commenter->name,
                ],
            ]);
        }
    }

    public function notifyCommentUpdated(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        $commenter = $comment->user;
        
        $usersToNotify = $this->getUsersToNotifyForComment($ticket, $commenter);
        
        foreach ($usersToNotify as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'comment_updated',
                'title' => 'Comment Updated',
                'message' => "{$commenter->name} updated a comment on ticket {$ticket->title}",
                'data' => [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'commenter_id' => $commenter->id,
                    'commenter_name' => $commenter->name,
                ],
            ]);
        }
    }

    private function getUsersToNotifyForComment(Ticket $ticket, User $commenter): Collection
    {
        $usersToNotify = collect();
        
        if ($ticket->creator && $ticket->creator->id !== $commenter->id) {
            $usersToNotify->push($ticket->creator);
        }
        
        $assignedUsers = $ticket->assignees()->where('users.id', '!=', $commenter->id)->get();
        $usersToNotify = $usersToNotify->merge($assignedUsers);
        
        $commenters = $ticket->comments()
            ->with('user')
            ->where('user_id', '!=', $commenter->id)
            ->get()
            ->pluck('user')
            ->unique('id');
        $usersToNotify = $usersToNotify->merge($commenters);
        
        return $usersToNotify->unique('id');
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();
            
        if ($notification) {
            $notification->markAsRead();
            return true;
        }
        
        return false;
    }

    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function notifyProjectAssignment(Project $project, User $assignedUser, User $assignedBy): void
    {
        // Create in-app notification
        try {
            Notification::create([
                'user_id' => $assignedUser->id,
                'type' => 'project_assigned',
                'title' => 'Ditambahkan ke Project',
                'message' => "Anda telah ditambahkan ke project '{$project->name}' oleh {$assignedBy->name}",
                'data' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'assigned_by_id' => $assignedBy->id,
                    'assigned_by_name' => $assignedBy->name,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $assignedUser->id,
            ]);
        }

        // Send email notification
        try {
            $mail = new ProjectAssignmentNotification($project, $assignedUser, $assignedBy);
            Mail::to($assignedUser->email)->send($mail);
        } catch (\Exception $e) {
            // Log error but don't fail the assignment
            Log::error('Failed to send project assignment email: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $assignedUser->id,
                'assigned_by_id' => $assignedBy->id,
                'to_email' => $assignedUser->email,
            ]);
        }
    }

    public function notifyProjectRemoval(Project $project, User $removedUser, User $removedBy): void
    {
        // Create in-app notification
        Notification::create([
            'user_id' => $removedUser->id,
            'type' => 'project_removed',
            'title' => 'Dihapus dari Project',
            'message' => "Anda telah dihapus dari project '{$project->name}' oleh {$removedBy->name}",
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'removed_by_id' => $removedBy->id,
                'removed_by_name' => $removedBy->name,
            ],
        ]);
    }
}
