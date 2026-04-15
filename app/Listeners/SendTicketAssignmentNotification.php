<?php

namespace App\Listeners;

use App\Events\TicketAssigneeChanged;
use App\Models\Notification;
use App\Models\User;
use App\Services\Hris\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTicketAssignmentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private PushNotificationService $pushService = new PushNotificationService()
    ) {}

    public function handle(TicketAssigneeChanged $event): void
    {
        if (empty($event->addedUserIds)) {
            return;
        }

        $ticket = $event->ticket;
        $actor = $event->actor;
        $actorName = $actor?->name ?? 'Someone';

        foreach ($event->addedUserIds as $userId) {
            // Skip if the actor is assigning themselves
            if ($actor && $userId === $actor->id) {
                continue;
            }

            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            $data = [
                'ticket_id'   => $ticket->id,
                'ticket_uuid' => $ticket->uuid,
                'ticket_name' => $ticket->name,
                'project_id'  => $ticket->project_id,
                'actor_id'    => $actor?->id,
                'actor_name'  => $actorName,
            ];

            // Create in-app notification
            try {
                Notification::create([
                    'user_id' => $user->id,
                    'type'    => 'ticket_assigned',
                    'title'   => 'Assigned to Ticket',
                    'message' => "You were assigned to ticket '{$ticket->name}' by {$actorName}",
                    'data'    => $data,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create ticket_assigned notification: ' . $e->getMessage(), [
                    'ticket_id' => $ticket->id,
                    'user_id'   => $user->id,
                ]);
            }

            // Send push notification
            try {
                $this->pushService->sendToUser(
                    $user,
                    'ticket_assigned',
                    '🎫 Ticket Assigned',
                    "You were assigned to '{$ticket->name}' by {$actorName}",
                    $data
                );
            } catch (\Exception $e) {
                Log::warning('Push failed for ticket_assigned: ' . $e->getMessage());
            }
        }
    }
}
