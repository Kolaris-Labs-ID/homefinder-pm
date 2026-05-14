<?php

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole($this->role);
    $this->actingAs($this->user);
});

test('project can be created', function () {
    $project = Project::factory()->create([
        'name' => 'Test Project',
    ]);
    
    expect($project)->toBeInstanceOf(Project::class);
    expect($project->name)->toBe('Test Project');
});

test('project can be updated', function () {
    $project = Project::factory()->create();
    
    $project->update(['name' => 'Updated Project']);
    
    expect($project->fresh()->name)->toBe('Updated Project');
});

test('project can be deleted', function () {
    $project = Project::factory()->create();
    
    $projectId = $project->id;
    $project->delete();
    
    expect(Project::find($projectId))->toBeNull();
});

test('ticket can be created', function () {
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create(['project_id' => $project->id]);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'name' => 'Test Ticket',
    ]);
    
    expect($ticket)->toBeInstanceOf(Ticket::class);
    expect($ticket->name)->toBe('Test Ticket');
});

test('ticket can be updated', function () {
    $project = Project::factory()->create();
    $status1 = TicketStatus::factory()->create(['project_id' => $project->id]);
    $status2 = TicketStatus::factory()->create(['project_id' => $project->id]);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status1->id,
    ]);
    
    $ticket->update(['ticket_status_id' => $status2->id]);
    
    expect($ticket->fresh()->ticket_status_id)->toBe($status2->id);
});

test('ticket can be deleted', function () {
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create(['project_id' => $project->id]);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
    ]);
    
    $ticketId = $ticket->id;
    $ticket->delete();
    
    expect(Ticket::find($ticketId))->toBeNull();
});

test('user can be created with division', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'division' => 'Engineering',
        'is_division_head' => false,
    ]);
    
    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBe('Test User');
    expect($user->division)->toBe('Engineering');
    expect($user->is_division_head)->toBeFalse();
});

test('user can be updated', function () {
    $user = User::factory()->create([
        'division' => 'Engineering',
        'is_division_head' => false,
    ]);
    
    $user->update([
        'division' => 'Marketing',
        'is_division_head' => true,
    ]);
    
    $user->refresh();
    expect($user->division)->toBe('Marketing');
    expect($user->is_division_head)->toBe(1); // Database stores as integer
});

test('user can be deleted', function () {
    $user = User::factory()->create();
    
    $userId = $user->id;
    $user->delete();
    
    expect(User::find($userId))->toBeNull();
});

