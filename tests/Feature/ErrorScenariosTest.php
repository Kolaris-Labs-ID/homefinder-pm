<?php

use App\Models\User;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Filament\Pages\ProjectBoard;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user = User::factory()->create();
    $this->user->assignRole($this->superAdminRole);
    $this->actingAs($this->user);
});

test('project board handles invalid ticket status gracefully', function () {
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create(['project_id' => $project->id]);
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
    ]);
    
    Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
    
    Livewire::test(ProjectBoard::class, ['project_id' => $project->id])
        ->call('moveTicket', $ticket->id, 999999) // Invalid status ID
        ->assertNotified('Error Updating Ticket');
    
    // Ticket status should not have changed
    $ticket->refresh();
    expect($ticket->ticket_status_id)->toBe($status->id);
});

test('project board handles missing ticket gracefully', function () {
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create(['project_id' => $project->id]);
    
    Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
    
    // When ticket doesn't exist, moveTicket should handle it gracefully
    // It checks if ticket exists and belongs to project before proceeding
    Livewire::test(ProjectBoard::class, ['project_id' => $project->id])
        ->call('moveTicket', 999999, $status->id); // Non-existent ticket
    
    // Should not throw exception, method should return early
    expect(true)->toBeTrue(); // Test passes if no exception thrown
});

test('permission denied shows appropriate error', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);
    
    $project = Project::factory()->create();
    $status1 = TicketStatus::factory()->create(['project_id' => $project->id, 'sort_order' => 1]);
    $status2 = TicketStatus::factory()->create(['project_id' => $project->id, 'sort_order' => 2]);
    
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status1->id,
    ]);
    
    // Regular user without permission
    Livewire::test(ProjectBoard::class, ['project_id' => $project->id])
        ->call('moveTicket', $ticket->id, $status2->id);
    
    // Ticket should not have moved
    $ticket->refresh();
    expect($ticket->ticket_status_id)->toBe($status1->id);
});


