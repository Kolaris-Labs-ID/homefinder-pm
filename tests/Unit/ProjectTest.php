<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\Epic;
use App\Models\ProjectNote;
use App\Models\ExternalAccess;
use Carbon\Carbon;

beforeEach(function () {
    $this->project = Project::factory()->create();
});

test('project can have many members', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $this->project->members()->attach([$user1->id, $user2->id]);

    expect($this->project->members)->toHaveCount(2);
    expect($this->project->members->first())->toBeInstanceOf(User::class);
});

test('project can have many tickets', function () {
    Ticket::factory()->count(3)->create(['project_id' => $this->project->id]);

    expect($this->project->tickets)->toHaveCount(3);
    expect($this->project->tickets->first())->toBeInstanceOf(Ticket::class);
});

test('project can have many ticket statuses', function () {
    TicketStatus::factory()->count(3)->create(['project_id' => $this->project->id]);

    expect($this->project->ticketStatuses)->toHaveCount(3);
});

test('project can have many epics', function () {
    Epic::factory()->count(2)->create(['project_id' => $this->project->id]);

    expect($this->project->epics)->toHaveCount(2);
});

test('project can have many notes', function () {
    ProjectNote::factory()->count(2)->create(['project_id' => $this->project->id]);

    expect($this->project->notes)->toHaveCount(2);
});

test('project can be pinned', function () {
    expect($this->project->is_pinned)->toBeFalse();

    $this->project->pin();

    expect($this->project->is_pinned)->toBeTrue();
    expect($this->project->pinned_date)->not->toBeNull();
});

test('project can be unpinned', function () {
    $this->project->pin();
    expect($this->project->is_pinned)->toBeTrue();

    $this->project->unpin();

    expect($this->project->is_pinned)->toBeFalse();
    expect($this->project->pinned_date)->toBeNull();
});

test('project can calculate remaining days', function () {
    $project = Project::factory()->create([
        'end_date' => Carbon::today()->addDays(10),
    ]);

    expect($project->remaining_days)->toBe(10);
});

test('project returns null remaining days when no end date', function () {
    $project = Project::factory()->withoutEndDate()->create();

    expect($project->remaining_days)->toBeNull();
});

test('project returns zero remaining days when past end date', function () {
    $project = Project::factory()->create([
        'end_date' => Carbon::yesterday(),
    ]);

    expect($project->remaining_days)->toBe(0);
});

test('project can calculate progress percentage', function () {
    $completedStatus = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => true,
    ]);
    
    $incompleteStatus = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'is_completed' => false,
    ]);

    Ticket::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $completedStatus->id,
    ]);

    Ticket::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $incompleteStatus->id,
    ]);

    expect($this->project->progress_percentage)->toBe(50.0);
});

test('project returns zero progress when no tickets', function () {
    expect($this->project->progress_percentage)->toBe(0.0);
});

test('project can have external access', function () {
    $externalAccess = ExternalAccess::factory()->create(['project_id' => $this->project->id]);

    expect($this->project->externalAccess)->toBeInstanceOf(ExternalAccess::class);
    expect($this->project->externalAccess->id)->toBe($externalAccess->id);
});

test('project can generate external access', function () {
    $externalAccess = $this->project->generateExternalAccess();

    expect($externalAccess)->toBeInstanceOf(ExternalAccess::class);
    expect($externalAccess->project_id)->toBe($this->project->id);
});

test('project dates are cast correctly', function () {
    $project = Project::factory()->create([
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
    ]);

    expect($project->start_date)->toBeInstanceOf(Carbon::class);
    expect($project->end_date)->toBeInstanceOf(Carbon::class);
});

