<?php

namespace App\Events;

use App\Models\ActivityLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ActivityLog $activityLog
    ) {}
}
