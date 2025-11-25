<?php

use App\Filament\Pages\ProjectBoard;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Models\TicketHistory;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->status1 = TicketStatus::factory()->create(['project_id' => $this->project->id, 'sort_order' => 1]);
    $this->status2 = TicketStatus::factory()->create(['project_id' => $this->project->id, 'sort_order' => 2]);
    
    $this->user = User::factory()->create();
    
    // Setup permissions
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user->assignRole($role);
    
    // Ensure permissions exist
    Permission::firstOrCreate(['name' => 'update_ticket', 'guard_name' => 'web']);
});

test('can move ticket', function () {
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status1->id,
    ]);

    auth()->login($this->user);

    Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id])
        ->call('moveTicket', $ticket->id, $this->status2->id)
        ->assertDispatched('ticket-updated');

    $ticket->refresh();
    expect($ticket->ticket_status_id)->toBe($this->status2->id);

    // Check history
    expect(TicketHistory::where('ticket_id', $ticket->id)->count())->toBeGreaterThan(0);
});

test('cannot move ticket without permission', function () {
    $user = User::factory()->create(); // Regular user without permission
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status1->id,
    ]);

    auth()->login($user);

    Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id])
        ->call('moveTicket', $ticket->id, $this->status2->id);

    $ticket->refresh();
    expect($ticket->ticket_status_id)->toBe($this->status1->id); // Should not change
});

test('move ticket with invalid status handles error gracefully', function () {
    $ticket = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status1->id,
    ]);

    auth()->login($this->user);
    
    // We expect no exception to be thrown, but a notification to be sent
    Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id])
        ->call('moveTicket', $ticket->id, 999999)
        ->assertNotified('Error Updating Ticket');
        
    $ticket->refresh();
    // Status should not have changed
    expect($ticket->ticket_status_id)->toBe($this->status1->id);
});

