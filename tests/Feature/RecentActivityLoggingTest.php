<?php

use App\Filament\Widgets\RecentActivityTable;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTicketWithProject(User $user): array
{
    $project = Project::factory()->create([
        'created_by' => $user->id,
    ]);

    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'name' => 'To Do',
    ]);

    $priority = TicketPriority::factory()->create();

    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $user->id,
    ]);

    return [$project, $status, $ticket];
}

it('logs project creation activities', function () {
    $user = User::factory()->create();

    Auth::login($user);

    $project = Project::factory()->create([
        'created_by' => $user->id,
        'name' => 'Apollo',
        'ticket_prefix' => 'APL',
    ]);

    $log = ActivityLog::where('event_type', 'project_created')
        ->where('subject_type', Project::class)
        ->where('subject_id', $project->id)
        ->first();

    expect($log)->not->toBeNull();
    expect($log->project_id)->toBe($project->id);
    expect($log->user_id)->toBe($user->id);
    expect($log->meta['ticket_prefix'])->toBe('APL');
});

it('logs ticket creation, status changes, and field edits', function () {
    $user = User::factory()->create();

    Auth::login($user);

    [$project, $status, $ticket] = createTicketWithProject($user);

    $nextStatus = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'name' => 'In Progress',
    ]);

    $ticket->update([
        'ticket_status_id' => $nextStatus->id,
        'name' => 'Updated ticket title',
    ]);

    expect(ActivityLog::where('event_type', 'ticket_created')->where('subject_id', $ticket->id)->count())->toBe(1);

    $statusLog = ActivityLog::where('event_type', 'ticket_status_changed')
        ->where('subject_id', $ticket->id)
        ->latest()
        ->first();

    expect($statusLog)->not->toBeNull();
    expect($statusLog->meta['from_status_name'])->toBe('To Do');
    expect($statusLog->meta['to_status_name'])->toBe('In Progress');

    $updateLog = ActivityLog::where('event_type', 'ticket_updated')
        ->where('subject_id', $ticket->id)
        ->latest()
        ->first();

    expect($updateLog)->not->toBeNull();
    expect($updateLog->meta['changed_fields'])->toContain('name');
    expect($updateLog->meta['changed_fields'])->not->toContain('ticket_status_id');
});

it('logs assignee-only ticket edits', function () {
    $user = User::factory()->create();
    $newAssignee = User::factory()->create();

    Auth::login($user);

    [, , $ticket] = createTicketWithProject($user);

    $ticket->syncAssigneesWithActivity([$newAssignee->id], $user);

    $updateLog = ActivityLog::where('event_type', 'ticket_updated')
        ->where('subject_id', $ticket->id)
        ->latest()
        ->first();

    expect($updateLog)->not->toBeNull();
    expect($updateLog->meta['changed_fields'])->toBe(['assignees']);
    expect($updateLog->meta['added_assignee_ids'])->toBe([$newAssignee->id]);
});

it('shows only member-visible activity records in the widget', function () {
    $creator = User::factory()->create();
    $viewer = User::factory()->create();

    Auth::login($creator);

    $visibleProject = Project::factory()->create(['created_by' => $creator->id]);
    $visibleProject->members()->attach($viewer->id);

    $hiddenProject = Project::factory()->create(['created_by' => $creator->id]);

    $visibleLog = ActivityLog::where('event_type', 'project_created')
        ->where('subject_id', $visibleProject->id)
        ->firstOrFail();

    $hiddenLog = ActivityLog::where('event_type', 'project_created')
        ->where('subject_id', $hiddenProject->id)
        ->firstOrFail();

    Livewire::actingAs($viewer)
        ->test(RecentActivityTable::class)
        ->assertCanSeeTableRecords([$visibleLog])
        ->assertCanNotSeeTableRecords([$hiddenLog]);
});
