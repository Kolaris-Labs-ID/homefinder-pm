<?php

namespace App\Listeners;

use App\Events\ProjectMemberAttached;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendProjectAssignmentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(ProjectMemberAttached $event): void
    {
        try {
            $this->notificationService->notifyProjectAssignment(
                $event->project,
                $event->user,
                $event->attachedBy
            );
        } catch (\Exception $e) {
            Log::error('Failed to process project assignment notification: ' . $e->getMessage(), [
                'project_id' => $event->project->id,
                'user_id' => $event->user->id,
            ]);
        }
    }
}
