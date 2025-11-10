<?php

use App\Models\Ticket;
use App\Models\Project;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use App\Models\User;
use App\Models\TicketHistory;
use App\Models\TicketComment;
use App\Models\Epic;
use Carbon\Carbon;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->status = TicketStatus::factory()->create(['project_id' => $this->project->id]);
    $this->priority = TicketPriority::factory()->create();
    $this->user = User::factory()->create();
});

test('ticket belongs to project', function () {
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
    ]);

    expect($ticket->project)->toBeInstanceOf(Project::class);
    expect($ticket->project->id)->toBe($this->project->id);
});

test('ticket belongs to status', function () {
    $ticket = Ticket::factory()->create([
        'ticket_status_id' => $this->status->id,
    ]);

    expect($ticket->status)->toBeInstanceOf(TicketStatus::class);
    expect($ticket->status->id)->toBe($this->status->id);
});

test('ticket belongs to priority', function () {
    $ticket = Ticket::factory()->create([
        'priority_id' => $this->priority->id,
    ]);

    expect($ticket->priority)->toBeInstanceOf(TicketPriority::class);
    expect($ticket->priority->id)->toBe($this->priority->id);
});

test('ticket can have many assignees', function () {
    $ticket = Ticket::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $ticket->assignees()->attach([$user1->id, $user2->id]);

    expect($ticket->assignees)->toHaveCount(2);
});

test('ticket can assign user', function () {
    $ticket = Ticket::factory()->create();
    
    $ticket->assignUser($this->user);

    expect($ticket->assignees()->where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('ticket can unassign user', function () {
    $ticket = Ticket::factory()->create();
    $ticket->assignees()->attach($this->user->id);

    $ticket->unassignUser($this->user);

    expect($ticket->assignees()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

test('ticket can assign multiple users', function () {
    $ticket = Ticket::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $ticket->assignUsers([$user1->id, $user2->id]);

    expect($ticket->assignees)->toHaveCount(2);
});

test('ticket can check if assigned to user', function () {
    $ticket = Ticket::factory()->create();
    
    expect($ticket->isAssignedTo($this->user))->toBeFalse();

    $ticket->assignUser($this->user);

    expect($ticket->isAssignedTo($this->user))->toBeTrue();
});

test('ticket generates uuid automatically on creation', function () {
    $ticket = Ticket::factory()->create([
        'uuid' => null,
        'project_id' => $this->project->id,
    ]);

    expect($ticket->uuid)->not->toBeNull();
    expect($ticket->uuid)->toStartWith($this->project->ticket_prefix);
});

test('ticket sets created_by automatically when user is authenticated', function () {
    auth()->login($this->user);

    $ticket = Ticket::factory()->create([
        'created_by' => null,
    ]);

    expect($ticket->created_by)->toBe($this->user->id);
});

test('ticket creates history when status changes', function () {
    $ticket = Ticket::factory()->create([
        'ticket_status_id' => $this->status->id,
    ]);

    auth()->login($this->user);

    $newStatus = TicketStatus::factory()->create(['project_id' => $this->project->id]);
    $ticket->update(['ticket_status_id' => $newStatus->id]);

    expect(TicketHistory::where('ticket_id', $ticket->id)->count())->toBe(1);
    expect(TicketHistory::where('ticket_id', $ticket->id)->first()->ticket_status_id)->toBe($newStatus->id);
});

test('ticket can have many histories', function () {
    $ticket = Ticket::factory()->create();
    
    TicketHistory::factory()->count(3)->create(['ticket_id' => $ticket->id]);

    expect($ticket->histories)->toHaveCount(3);
});

test('ticket can have many comments', function () {
    $ticket = Ticket::factory()->create();
    
    TicketComment::factory()->count(2)->create(['ticket_id' => $ticket->id]);

    expect($ticket->comments)->toHaveCount(2);
});

test('ticket can belong to epic', function () {
    $epic = Epic::factory()->create(['project_id' => $this->project->id]);
    $ticket = Ticket::factory()->create(['epic_id' => $epic->id]);

    expect($ticket->epic)->toBeInstanceOf(Epic::class);
    expect($ticket->epic->id)->toBe($epic->id);
});

test('ticket can check if overdue', function () {
    $ticket = Ticket::factory()->overdue()->create([
        'ticket_status_id' => $this->status->id,
    ]);

    expect($ticket->isOverdue())->toBeTrue();
});

test('ticket is not overdue if completed', function () {
    $completedStatus = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => true,
    ]);

    $ticket = Ticket::factory()->overdue()->create([
        'ticket_status_id' => $completedStatus->id,
    ]);

    expect($ticket->isOverdue())->toBeFalse();
});

test('ticket is not overdue if no due date', function () {
    $ticket = Ticket::factory()->withoutDueDate()->create();

    expect($ticket->isOverdue())->toBeFalse();
});

test('ticket can get days until due', function () {
    $ticket = Ticket::factory()->create([
        'due_date' => Carbon::today()->addDays(5),
    ]);

    expect($ticket->getDaysUntilDue())->toBe(5);
});

test('ticket returns null days until due when no due date', function () {
    $ticket = Ticket::factory()->withoutDueDate()->create();

    expect($ticket->getDaysUntilDue())->toBeNull();
});

test('ticket can check if nearing overdue', function () {
    $ticket = Ticket::factory()->nearingOverdue()->create([
        'ticket_status_id' => $this->status->id,
    ]);

    expect($ticket->isNearingOverdue(3))->toBeTrue();
});

test('ticket scope can get overdue tickets', function () {
    $completedStatus = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => true,
    ]);

    Ticket::factory()->overdue()->create([
        'ticket_status_id' => $this->status->id,
    ]);
    Ticket::factory()->overdue()->create([
        'ticket_status_id' => $completedStatus->id,
    ]);
    Ticket::factory()->create([
        'due_date' => Carbon::today()->addDays(5),
        'ticket_status_id' => $this->status->id,
    ]);

    $overdueTickets = Ticket::overdue()->get();

    expect($overdueTickets)->toHaveCount(1);
});

test('ticket scope can get nearing overdue tickets', function () {
    Ticket::factory()->nearingOverdue()->create([
        'ticket_status_id' => $this->status->id,
    ]);
    Ticket::factory()->create([
        'due_date' => Carbon::today()->addDays(10),
        'ticket_status_id' => $this->status->id,
    ]);

    $nearingOverdue = Ticket::nearingOverdue(3)->get();

    expect($nearingOverdue)->toHaveCount(1);
});

test('ticket dates are cast correctly', function () {
    $ticket = Ticket::factory()->create([
        'start_date' => '2024-01-01',
        'due_date' => '2024-12-31',
    ]);

    expect($ticket->start_date)->toBeInstanceOf(Carbon::class);
    expect($ticket->due_date)->toBeInstanceOf(Carbon::class);
});

