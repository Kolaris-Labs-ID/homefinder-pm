<?php

namespace App\Services;

use App\Mail\ProjectAssignmentNotification;
use App\Mail\TicketOverdueNotification;
use App\Mail\TicketNearingOverdueNotification;
use App\Mail\ProjectOverdueNotification;
use App\Mail\ProjectNearingOverdueNotification;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Hris\PushNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class NotificationService
{
    public function __construct(
        private PushNotificationService $pushService = new PushNotificationService()
    ) {}
    public function notifyCommentAdded(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        $commenter = $comment->user;

        $usersToNotify = $this->getUsersToNotifyForComment($ticket, $commenter);

        foreach ($usersToNotify as $user) {
            $notifData = array_merge($this->actorData($commenter), [
                'ticket_id' => $ticket->id,
                'ticket_name' => $ticket->name,
                'comment_id' => $comment->id,
            ]);
            Notification::create([
                'user_id' => $user->id,
                'type' => 'comment_added',
                'title' => 'New Comment on Ticket',
                'message' => "{$commenter->name} added a comment on ticket {$ticket->name}",
                'data' => $notifData,
            ]);
            // Push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'comment_added',
                    '💬 New Comment',
                    "{$commenter->name} commented on {$ticket->name}",
                    $notifData
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for comment_added: ' . $e->getMessage());
            }
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
                'message' => "{$commenter->name} updated a comment on ticket {$ticket->name}",
                'data' => array_merge($this->actorData($commenter), [
                    'ticket_id' => $ticket->id,
                    'ticket_name' => $ticket->name,
                    'comment_id' => $comment->id,
                ]),
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
        $pushData = array_merge($this->actorData($assignedBy), [
            'project_id' => $project->id,
            'project_name' => $project->name,
        ]);

        // Create in-app notification
        try {
            Notification::create([
                'user_id' => $assignedUser->id,
                'type' => 'project_assigned',
                'title' => 'Ditambahkan ke Project',
                'message' => "Anda telah ditambahkan ke project '{$project->name}' oleh {$assignedBy->name}",
                'data' => $pushData,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create in-app notification: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $assignedUser->id,
            ]);
        }

        // Send push notification
        try {
            $this->pushService->sendToUser(
                $assignedUser,
                'project_assigned',
                '📁 Added to Project',
                "You were added to project '{$project->name}' by {$assignedBy->name}",
                $pushData
            );
        } catch (\Exception $e) {
            Log::warning('Push failed for project_assigned: ' . $e->getMessage());
        }

        // Send email notification
        try {
            $mail = new ProjectAssignmentNotification($project, $assignedUser, $assignedBy);
            Mail::to($assignedUser->email)->send($mail);
        } catch (\Exception $e) {
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
            'data' => array_merge($this->actorData($removedBy), [
                'project_id' => $project->id,
                'project_name' => $project->name,
            ]),
        ]);
    }

    /**
     * Notify users about overdue ticket
     */
    public function notifyTicketOverdue(Ticket $ticket): void
    {
        $usersToNotify = $this->getUsersToNotifyForTicket($ticket);
        $overdueDays = Carbon::parse($ticket->due_date)->diffInDays(Carbon::today());

        foreach ($usersToNotify as $user) {
            // Skip if already notified today for this ticket
            if ($this->alreadyNotifiedToday($user->id, 'ticket_overdue', $ticket->id)) {
                continue;
            }

            // Create in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'ticket_overdue',
                    'title' => 'Ticket Overdue',
                    'message' => "Ticket '{$ticket->name}' ({$ticket->uuid}) is overdue by {$overdueDays} day(s)",
                    'data' => array_merge($this->actorData(null), [
                        'ticket_id' => $ticket->id,
                        'ticket_uuid' => $ticket->uuid,
                        'ticket_name' => $ticket->name,
                        'project_id' => $ticket->project_id,
                        'project_name' => $ticket->project->name ?? 'Unknown',
                        'due_date' => $ticket->due_date?->format('Y-m-d'),
                        'overdue_days' => $overdueDays,
                    ]),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create overdue ticket notification: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                ]);
            }

            // Send push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'ticket_overdue',
                    '🔴 Ticket Overdue',
                    "Ticket '{$ticket->name}' is overdue by {$overdueDays} day(s)",
                    [
                        'ticket_id' => $ticket->id,
                        'ticket_uuid' => $ticket->uuid,
                        'ticket_name' => $ticket->name,
                        'project_id' => $ticket->project_id,
                        'overdue_days' => $overdueDays,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for ticket_overdue: ' . $e->getMessage());
            }

            // Send email notification
            try {
                $mail = new TicketOverdueNotification($ticket, $user, $overdueDays);
                Mail::to($user->email)->send($mail);
            } catch (\Exception $e) {
                Log::error('Failed to send overdue ticket email: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                ]);
            }
        }
    }

    /**
     * Notify users about ticket nearing overdue
     */
    public function notifyTicketNearingOverdue(Ticket $ticket, int $daysUntilDue): void
    {
        $usersToNotify = $this->getUsersToNotifyForTicket($ticket);

        foreach ($usersToNotify as $user) {
            // Skip if already notified today for this ticket
            if ($this->alreadyNotifiedToday($user->id, 'ticket_nearing_overdue', $ticket->id)) {
                continue;
            }

            // Create in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'ticket_nearing_overdue',
                    'title' => 'Ticket Due Soon',
                    'message' => "Ticket '{$ticket->name}' ({$ticket->uuid}) is due in {$daysUntilDue} day(s)",
                    'data' => array_merge($this->actorData(null), [
                        'ticket_id' => $ticket->id,
                        'ticket_uuid' => $ticket->uuid,
                        'ticket_name' => $ticket->name,
                        'project_id' => $ticket->project_id,
                        'project_name' => $ticket->project->name ?? 'Unknown',
                        'due_date' => $ticket->due_date?->format('Y-m-d'),
                        'days_until_due' => $daysUntilDue,
                    ]),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create nearing overdue ticket notification: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                ]);
            }

            // Send push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'ticket_nearing_overdue',
                    '⚠️ Ticket Due Soon',
                    "Ticket '{$ticket->name}' is due in {$daysUntilDue} day(s)",
                    [
                        'ticket_id' => $ticket->id,
                        'ticket_uuid' => $ticket->uuid,
                        'ticket_name' => $ticket->name,
                        'project_id' => $ticket->project_id,
                        'days_until_due' => $daysUntilDue,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for ticket_nearing_overdue: ' . $e->getMessage());
            }

            // Send email notification
            try {
                $mail = new TicketNearingOverdueNotification($ticket, $user, $daysUntilDue);
                Mail::to($user->email)->send($mail);
            } catch (\Exception $e) {
                Log::error('Failed to send nearing overdue ticket email: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                ]);
            }
        }
    }

    /**
     * Notify users about overdue project
     */
    public function notifyProjectOverdue(Project $project): void
    {
        if (!$project->end_date) {
            return;
        }

        $usersToNotify = $project->members;
        $overdueDays = Carbon::parse($project->end_date)->diffInDays(Carbon::today());

        foreach ($usersToNotify as $user) {
            // Skip if already notified today for this project
            if ($this->alreadyNotifiedToday($user->id, 'project_overdue', null, $project->id)) {
                continue;
            }

            // Create in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'project_overdue',
                    'title' => 'Project Overdue',
                    'message' => "Project '{$project->name}' is overdue by {$overdueDays} day(s)",
                    'data' => array_merge($this->actorData(null), [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'end_date' => $project->end_date?->format('Y-m-d'),
                        'overdue_days' => $overdueDays,
                    ]),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create overdue project notification: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                ]);
            }

            // Send push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'project_overdue',
                    '🔴 Project Overdue',
                    "Project '{$project->name}' is overdue by {$overdueDays} day(s)",
                    ['project_id' => $project->id, 'project_name' => $project->name, 'overdue_days' => $overdueDays]
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for project_overdue: ' . $e->getMessage());
            }

            // Send email notification
            try {
                $mail = new ProjectOverdueNotification($project, $user, $overdueDays);
                Mail::to($user->email)->send($mail);
            } catch (\Exception $e) {
                Log::error('Failed to send overdue project email: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                ]);
            }
        }
    }

    /**
     * Notify users about project nearing overdue
     */
    public function notifyProjectNearingOverdue(Project $project, ?int $daysUntilDue): void
    {
        if (!$project->end_date || $daysUntilDue === null) {
            return;
        }

        $usersToNotify = $project->members;

        foreach ($usersToNotify as $user) {
            // Skip if already notified today for this project
            if ($this->alreadyNotifiedToday($user->id, 'project_nearing_overdue', null, $project->id)) {
                continue;
            }

            // Create in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type' => 'project_nearing_overdue',
                    'title' => 'Project Due Soon',
                    'message' => "Project '{$project->name}' is due in {$daysUntilDue} day(s)",
                    'data' => array_merge($this->actorData(null), [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'end_date' => $project->end_date?->format('Y-m-d'),
                        'days_until_due' => $daysUntilDue,
                    ]),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create nearing overdue project notification: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                ]);
            }

            // Send push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'project_nearing_overdue',
                    '⚠️ Project Due Soon',
                    "Project '{$project->name}' is due in {$daysUntilDue} day(s)",
                    ['project_id' => $project->id, 'project_name' => $project->name, 'days_until_due' => $daysUntilDue]
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for project_nearing_overdue: ' . $e->getMessage());
            }

            // Send email notification
            try {
                $mail = new ProjectNearingOverdueNotification($project, $user, $daysUntilDue);
                Mail::to($user->email)->send($mail);
            } catch (\Exception $e) {
                Log::error('Failed to send nearing overdue project email: ' . $e->getMessage(), [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                ]);
            }
        }
    }

    /**
     * Get users to notify for a ticket (assignees, creator, project members)
     */
    private function getUsersToNotifyForTicket(Ticket $ticket): Collection
    {
        $usersToNotify = collect();

        // Add assignees
        $assignees = $ticket->assignees;
        $usersToNotify = $usersToNotify->merge($assignees);

        // Add creator
        if ($ticket->creator) {
            $usersToNotify->push($ticket->creator);
        }

        // Add project members (if project exists)
        if ($ticket->project) {
            $projectMembers = $ticket->project->members;
            $usersToNotify = $usersToNotify->merge($projectMembers);
        }

        return $usersToNotify->unique('id');
    }

    private function actorData(?User $user, string $fallbackName = 'System'): array
    {
        if (! $user) {
            return [
                'actor_id' => null,
                'actor_name' => $fallbackName,
                'actor_avatar' => null,
                'actor_verified' => false,
            ];
        }

        return [
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'actor_avatar' => $user->avatar_url,
            'actor_verified' => method_exists($user, 'hasRole') ? $user->hasRole('super_admin') : false,
        ];
    }

    /**
     * Check if a notification of the same type was already sent today for a given ticket or project.
     */
    private function alreadyNotifiedToday(int $userId, string $type, ?int $ticketId = null, ?int $projectId = null): bool
    {
        $query = Notification::where('user_id', $userId)
            ->where('type', $type)
            ->whereDate('created_at', Carbon::today());

        if ($ticketId) {
            $query->where('data->ticket_id', $ticketId);
        }

        if ($projectId) {
            $query->where('data->project_id', $projectId);
        }

        return $query->exists();
    }
}
