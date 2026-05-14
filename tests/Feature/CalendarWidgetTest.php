<?php

use App\Filament\Widgets\CalendarWidget;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\Epic;
use App\Models\User;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'start_date' => Carbon::now()->subDays(10),
        'end_date' => Carbon::now()->addDays(20),
    ]);
    $this->status = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => false,
    ]);
    $this->completedStatus = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => true,
    ]);
    $this->priority = TicketPriority::factory()->create([
        'color' => '#FF5733',
    ]);
});

test('calendar widget can be instantiated', function () {
    $widget = new CalendarWidget();
    
    expect($widget)->toBeInstanceOf(CalendarWidget::class);
    expect($widget::canView())->toBeFalse(); // No authenticated user
});

test('calendar widget requires authentication', function () {
    expect(CalendarWidget::canView())->toBeFalse();
    
    $this->actingAs($this->user);
    expect(CalendarWidget::canView())->toBeTrue();
});

test('calendar widget generates events for projects', function () {
    $this->actingAs($this->user);
    
    // Attach user to project
    $this->project->members()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $projectEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'project_'));
    expect($projectEvents)->not->toBeEmpty();
    
    $projectEvent = collect($projectEvents)->first();
    expect($projectEvent['title'])->toContain($this->project->name);
    expect($projectEvent['start'])->toBe($this->project->start_date->format('Y-m-d'));
    expect($projectEvent['end'])->toBe($this->project->end_date->copy()->addDay()->format('Y-m-d'));
    expect($projectEvent['backgroundColor'])->toBe('#3b82f6');
    expect($projectEvent['extendedProps']['type'])->toBe('project');
});

test('calendar widget filters projects by membership - regular user sees only member projects', function () {
    $this->actingAs($this->user);
    
    $otherProject = Project::factory()->create([
        'start_date' => Carbon::now()->subDays(5),
        'end_date' => Carbon::now()->addDays(15),
    ]);
    
    // Attach user only to first project
    $this->project->members()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $projectEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'project_'));
    expect(count($projectEvents))->toBe(1);
    expect(collect($projectEvents)->first()['extendedProps']['url'])->toContain($this->project->id);
});

test('calendar widget generates events for tickets', function () {
    $this->actingAs($this->user);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'due_date' => Carbon::now()->addDays(7),
        'start_date' => Carbon::now()->addDays(1),
    ]);
    
    // Assign ticket to user
    $ticket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    expect($ticketEvents)->not->toBeEmpty();
    
    $ticketEvent = collect($ticketEvents)->first();
    expect($ticketEvent['title'])->toContain($ticket->uuid);
    expect($ticketEvent['start'])->toBe($ticket->start_date->format('Y-m-d'));
    expect($ticketEvent['end'])->toBe($ticket->due_date->format('Y-m-d'));
    expect($ticketEvent['extendedProps']['type'])->toBe('ticket');
    expect($ticketEvent['extendedProps']['uuid'])->toBe($ticket->uuid);
});

test('calendar widget filters tickets by assignment - regular user sees only assigned tickets', function () {
    $this->actingAs($this->user);
    
    $assignedTicket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'due_date' => Carbon::now()->addDays(7),
    ]);
    $assignedTicket->assignees()->attach($this->user->id);
    
    $unassignedTicket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'due_date' => Carbon::now()->addDays(8),
    ]);
    $unassignedTicket->assignees()->attach($this->otherUser->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    expect(count($ticketEvents))->toBe(1);
    expect(collect($ticketEvents)->first()['extendedProps']['uuid'])->toBe($assignedTicket->uuid);
});

test('calendar widget uses ticket start_date when available, otherwise falls back to due_date minus one day', function () {
    $this->actingAs($this->user);
    
    $ticketWithStartDate = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'start_date' => Carbon::now()->addDays(2),
        'due_date' => Carbon::now()->addDays(7),
    ]);
    $ticketWithStartDate->assignees()->attach($this->user->id);
    
    $ticketWithoutStartDate = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'start_date' => null,
        'due_date' => Carbon::now()->addDays(8),
    ]);
    $ticketWithoutStartDate->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    expect(count($ticketEvents))->toBe(2);
    
    $eventWithStart = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $ticketWithStartDate->uuid);
    expect($eventWithStart['start'])->toBe($ticketWithStartDate->start_date->format('Y-m-d'));
    
    $eventWithoutStart = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $ticketWithoutStartDate->uuid);
    expect($eventWithoutStart['start'])->toBe($ticketWithoutStartDate->due_date->copy()->subDays(1)->format('Y-m-d'));
});

test('calendar widget colors tickets correctly - completed tickets are green', function () {
    $this->actingAs($this->user);
    
    $completedTicket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->completedStatus->id,
        'priority_id' => $this->priority->id,
        'due_date' => Carbon::now()->addDays(7),
    ]);
    $completedTicket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    $completedEvent = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $completedTicket->uuid);
    
    expect($completedEvent['backgroundColor'])->toBe('#10b981'); // green
    expect($completedEvent['extendedProps']['isCompleted'])->toBeTrue();
});

test('calendar widget colors tickets correctly - overdue tickets are red', function () {
    $this->actingAs($this->user);
    
    $overdueTicket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'due_date' => Carbon::now()->subDays(5), // Past due date
    ]);
    $overdueTicket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    $overdueEvent = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $overdueTicket->uuid);
    
    expect($overdueEvent['backgroundColor'])->toBe('#ef4444'); // red
    expect($overdueEvent['extendedProps']['isOverdue'])->toBeTrue();
});

test('calendar widget uses priority color for tickets when available', function () {
    $this->actingAs($this->user);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'due_date' => Carbon::now()->addDays(7), // Future date, not overdue
    ]);
    $ticket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    $ticketEvent = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $ticket->uuid);
    
    expect($ticketEvent['backgroundColor'])->toBe('#FF5733'); // Priority color
});

test('calendar widget uses default blue color when ticket has no priority color', function () {
    $this->actingAs($this->user);
    
    // Create priority and then set color to empty string (simulating no color)
    $priorityWithoutColor = TicketPriority::factory()->create();
    $priorityWithoutColor->update(['color' => '']);
    $priorityWithoutColor->refresh();
    
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'priority_id' => $priorityWithoutColor->id,
        'due_date' => Carbon::now()->addDays(7),
    ]);
    $ticket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    $ticketEvent = collect($ticketEvents)->first(fn($e) => $e['extendedProps']['uuid'] === $ticket->uuid);
    
    expect($ticketEvent['backgroundColor'])->toBe('#3b82f6'); // default blue
});

test('calendar widget excludes projects without start_date or end_date', function () {
    $this->actingAs($this->user);
    
    $projectWithoutDates = Project::factory()->create([
        'start_date' => null,
        'end_date' => null,
    ]);
    $projectWithoutDates->members()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $projectEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'project_'));
    expect($projectEvents)->toBeEmpty();
});

test('calendar widget excludes tickets without due_date', function () {
    $this->actingAs($this->user);
    
    $ticketWithoutDueDate = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'due_date' => null,
    ]);
    $ticketWithoutDueDate->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $ticketEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'ticket_'));
    expect($ticketEvents)->toBeEmpty();
});

test('calendar widget generates correct URLs for events', function () {
    $this->actingAs($this->user);
    
    $this->project->members()->attach($this->user->id);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'due_date' => Carbon::now()->addDays(7),
    ]);
    $ticket->assignees()->attach($this->user->id);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $projectEvent = collect($events)->first(fn($e) => str_starts_with($e['id'], 'project_'));
    expect($projectEvent['extendedProps']['url'])->toContain('projects');
    expect($projectEvent['extendedProps']['url'])->toContain($this->project->id);
    
    $ticketEvent = collect($events)->first(fn($e) => str_starts_with($e['id'], 'ticket_'));
    expect($ticketEvent['extendedProps']['url'])->toContain('tickets');
    expect($ticketEvent['extendedProps']['url'])->toContain($ticket->id);
});

test('calendar widget generates events for epics', function () {
    $this->actingAs($this->user);
    
    // Add user to project
    $this->project->members()->attach($this->user->id);
    
    $epic = Epic::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => Carbon::now()->addDays(3),
        'end_date' => Carbon::now()->addDays(10),
    ]);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $epicEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'epic_'));
    expect(count($epicEvents))->toBe(1);
    
    $epicEvent = $epicEvents[array_key_first($epicEvents)];
    expect($epicEvent['title'])->toContain($epic->name);
    expect($epicEvent['extendedProps']['type'])->toBe('epic');
    expect($epicEvent['backgroundColor'])->toBe('#8b5cf6'); // Purple color for epics
});

test('calendar widget filters epics by project membership', function () {
    $this->actingAs($this->user);
    
    // User is member of project
    $this->project->members()->attach($this->user->id);
    
    // Other project user is NOT a member of
    $otherProject = Project::factory()->create([
        'start_date' => Carbon::now()->subDays(5),
        'end_date' => Carbon::now()->addDays(15),
    ]);
    
    // Epic in user's project
    $userEpic = Epic::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => Carbon::now()->addDays(3),
        'end_date' => Carbon::now()->addDays(10),
    ]);
    
    // Epic in other project
    $otherEpic = Epic::factory()->create([
        'project_id' => $otherProject->id,
        'start_date' => Carbon::now()->addDays(5),
        'end_date' => Carbon::now()->addDays(12),
    ]);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $epicEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'epic_'));
    expect(count($epicEvents))->toBe(1); // Only epic from user's project
    
    $epicEvent = $epicEvents[array_key_first($epicEvents)];
    expect($epicEvent['id'])->toBe('epic_' . $userEpic->id);
});

test('calendar widget excludes epics without start_date or end_date', function () {
    $this->actingAs($this->user);
    
    // Add user to project
    $this->project->members()->attach($this->user->id);
    
    // Epic with dates
    Epic::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => Carbon::now()->addDays(3),
        'end_date' => Carbon::now()->addDays(10),
    ]);
    
    // Epic without start_date
    Epic::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => null,
        'end_date' => Carbon::now()->addDays(10),
    ]);
    
    // Epic without end_date
    Epic::factory()->create([
        'project_id' => $this->project->id,
        'start_date' => Carbon::now()->addDays(3),
        'end_date' => null,
    ]);
    
    $widget = new CalendarWidget();
    $events = $widget->getCalendarEvents();
    
    $epicEvents = array_filter($events, fn($e) => str_starts_with($e['id'], 'epic_'));
    expect(count($epicEvents))->toBe(1); // Only epic with both dates
});

