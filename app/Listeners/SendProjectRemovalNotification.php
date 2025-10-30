<?php

namespace App\Listeners;

use App\Events\ProjectMemberDetached;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendProjectRemovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(ProjectMemberDetached $event): void
    {
        $this->notificationService->notifyProjectRemoval(
            $event->project,
            $event->user,
            $event->detachedBy
        );
    }
}
