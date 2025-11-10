<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\DailyTodo;
use App\Models\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('user can have many projects', function () {
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();

    $this->user->projects()->attach([$project1->id, $project2->id]);

    expect($this->user->projects)->toHaveCount(2);
    expect($this->user->projects->first())->toBeInstanceOf(Project::class);
});

test('user can have many tickets', function () {
    Ticket::factory()->count(3)->create(['created_by' => $this->user->id]);

    expect($this->user->createdTickets)->toHaveCount(3);
    expect($this->user->createdTickets->first())->toBeInstanceOf(Ticket::class);
});

test('user can have assigned tickets', function () {
    $ticket1 = Ticket::factory()->create();
    $ticket2 = Ticket::factory()->create();

    $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id]);

    expect($this->user->assignedTickets)->toHaveCount(2);
});

test('user can check if assigned to ticket', function () {
    $ticket = Ticket::factory()->create();
    
    expect($this->user->isAssignedToTicket($ticket))->toBeFalse();

    $this->user->assignToTicket($ticket);

    expect($this->user->isAssignedToTicket($ticket))->toBeTrue();
});

test('user can assign to ticket', function () {
    $ticket = Ticket::factory()->create();
    
    $this->user->assignToTicket($ticket);

    expect($this->user->assignedTickets()->where('ticket_id', $ticket->id)->exists())->toBeTrue();
});

test('user can have daily todos', function () {
    DailyTodo::factory()->count(2)->create(['user_id' => $this->user->id]);

    expect($this->user->dailyTodos)->toHaveCount(2);
});

test('user can have notifications', function () {
    Notification::factory()->count(3)->create(['user_id' => $this->user->id]);

    expect($this->user->notifications)->toHaveCount(3);
});

test('user can get unread notifications', function () {
    Notification::factory()->create(['user_id' => $this->user->id, 'read_at' => null]);
    Notification::factory()->create(['user_id' => $this->user->id, 'read_at' => now()]);
    Notification::factory()->create(['user_id' => $this->user->id, 'read_at' => null]);

    expect($this->user->unreadNotifications)->toHaveCount(2);
});

test('user can get unread notifications count attribute', function () {
    Notification::factory()->create(['user_id' => $this->user->id, 'read_at' => null]);
    Notification::factory()->create(['user_id' => $this->user->id, 'read_at' => null]);

    expect($this->user->unread_notifications_count)->toBe(2);
});

test('user can access panel if has any role', function () {
    $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
    
    expect($this->user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();

    $this->user->assignRole($role);

    expect($this->user->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

test('user password is hashed', function () {
    $user = User::factory()->create(['password' => 'plain-password']);

    expect($user->password)->not->toBe('plain-password');
    expect(\Hash::check('plain-password', $user->password))->toBeTrue();
});

