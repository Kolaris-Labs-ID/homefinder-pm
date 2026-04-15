<?php

namespace App\Listeners;

use App\Events\ActivityLogCreated;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Hris\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendActivityLogNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private PushNotificationService $pushService = new PushNotificationService()
    ) {}

    public function handle(ActivityLogCreated $event): void
    {
        $log     = $event->activityLog;
        $actor   = $log->user;        // User who performed the action (may be null)
        $actorId = $actor?->id;

        // Only handle ticket-related activity logs
        if ($log->subject_type !== Ticket::class) {
            return;
        }

        $ticket = $log->subject; // morphTo resolves to Ticket
        if (!$ticket) {
            return;
        }

        $title = $this->buildTitle($log->event_type, $log->meta ?? []);
        $body  = $this->buildBody($log->event_type, $actor, $ticket, $log->meta ?? []);

        $data = [
            'ticket_id'   => $ticket->id,
            'ticket_uuid' => $ticket->uuid,
            'ticket_name' => $ticket->name,
            'project_id'  => $ticket->project_id,
            'event_type'  => $log->event_type,
            'actor_id'    => $actorId,
            'actor_name'  => $actor?->name ?? 'System',
        ];

        $recipients = $this->resolveRecipients($ticket, $actorId);

        foreach ($recipients as $user) {
            try {
                $this->pushService->sendToUser($user, 'activity_log', $title, $body, $data);
            } catch (\Exception $e) {
                Log::warning('Push failed for activity_log: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id'   => $user->id,
                ]);
            }
        }
    }

    /**
     * Resolve who should receive the push based on role hierarchy:
     * - Assignees of the ticket (excluding actor)
     * - Head(s) of the same division as any assignee (excluding actor)
     * - C-Level / super_admin users (excluding actor)
     */
    private function resolveRecipients(Ticket $ticket, ?int $actorId): Collection
    {
        $recipients = collect();

        // 1. Assignees of the ticket
        $assignees = $ticket->assignees;
        $recipients = $recipients->merge($assignees);

        // 2. Division heads — find all users where is_division_head = true
        //    whose division matches any assignee's division
        $assigneeDivisions = $assignees
            ->pluck('division')
            ->filter()
            ->unique()
            ->values();

        if ($assigneeDivisions->isNotEmpty()) {
            $divisionHeads = User::where('is_division_head', true)
                ->whereIn('division', $assigneeDivisions)
                ->get();
            $recipients = $recipients->merge($divisionHeads);
        }

        // 3. C-Level / super_admin users — canSeeAllPmData() users
        $globalViewers = User::all()->filter(fn (User $u) => $u->canSeeAllPmData());
        $recipients = $recipients->merge($globalViewers);

        // Deduplicate and exclude the actor
        return $recipients
            ->unique('id')
            ->filter(fn (User $u) => $u->id !== $actorId)
            ->values();
    }

    private function buildTitle(string $eventType, array $meta): string
    {
        return match ($eventType) {
            'ticket_created'        => '🎫 New Ticket Created',
            'ticket_updated'        => '✏️ Ticket Updated',
            'ticket_status_changed' => '🔄 Ticket Status Changed',
            'ticket_comment_created'=> '💬 New Comment',
            default                 => '📋 Ticket Activity',
        };
    }

    private function buildBody(string $eventType, ?User $actor, Ticket $ticket, array $meta): string
    {
        $actorName  = $actor?->name ?? 'System';
        $ticketName = $ticket->name;

        return match ($eventType) {
            'ticket_created'         => "{$actorName} created ticket '{$ticketName}'",
            'ticket_updated'         => "{$actorName} updated ticket '{$ticketName}'",
            'ticket_status_changed'  => "{$actorName} moved '{$ticketName}' to " . ($meta['to_status_name'] ?? 'new status'),
            'ticket_comment_created' => "{$actorName} commented on '{$ticketName}'",
            default                  => "{$actorName} performed an action on '{$ticketName}'",
        };
    }
}
