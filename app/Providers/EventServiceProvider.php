<?php

namespace App\Providers;

use App\Events\ActivityLogCreated;
use App\Events\ProjectMemberAttached;
use App\Events\ProjectMemberDetached;
use App\Events\TicketAssigneeChanged;
use App\Listeners\SendActivityLogNotification;
use App\Listeners\SendProjectAssignmentNotification;
use App\Listeners\SendProjectRemovalNotification;
use App\Listeners\SendTicketAssignmentNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        ProjectMemberAttached::class => [
            SendProjectAssignmentNotification::class,
        ],
        ProjectMemberDetached::class => [
            SendProjectRemovalNotification::class,
        ],
        TicketAssigneeChanged::class => [
            SendTicketAssignmentNotification::class,
        ],
        ActivityLogCreated::class => [
            SendActivityLogNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
