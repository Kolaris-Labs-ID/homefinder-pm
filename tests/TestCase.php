<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property \App\Models\Project $project
 * @property \App\Models\TicketStatus $status
 * @property \App\Models\TicketPriority $priority
 * @property \App\Models\User $user
 * @property \Spatie\Permission\Models\Role $role
 */
abstract class TestCase extends BaseTestCase
{
    public $project;
    public $status;
    public $priority;
    public $user;
    public $role;
}
