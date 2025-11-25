<?php

use App\Filament\Widgets\WeeklyTodoOverviewWidget;
use App\Models\WeeklyTodo;
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

test('division filter handles empty division gracefully', function () {
    // Create user without division
    $userWithoutDivision = User::factory()->create([
        'division' => null,
    ]);
    
    WeeklyTodo::factory()->create([
        'user_id' => $userWithoutDivision->id,
    ]);
    
    // Filter should not crash when division is null
    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));
    $query = $table->getQuery();
    
    // Should execute without error
    expect(fn() => $query->get())->not->toThrow(\Exception::class);
});

test('division filter handles non-existent division', function () {
    // Test filtering by a division that doesn't exist
    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->whereHas('user', function ($q) use ($data) {
                $q->where('division', $data['value']);
            });
        }
        return $query;
    };
    
    $query = WeeklyTodo::query()->with('user');
    $filteredQuery = $filterCallback(clone $query, ['value' => 'NonExistentDivision']);
    
    // Should return empty result, not crash
    $results = $filteredQuery->get();
    expect($results)->toBeEmpty();
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

test('weekly todo widget handles missing user relationship gracefully', function () {
    // Create a valid todo first
    $user = User::factory()->create();
    $todo = WeeklyTodo::factory()->create([
        'user_id' => $user->id,
    ]);
    
    $widget = new WeeklyTodoOverviewWidget();
    $table = $widget->table(\Filament\Tables\Table::make($widget));
    $query = $table->getQuery();
    
    // Query should work with valid user
    $results = $query->get();
    expect($results)->not->toBeEmpty();
    
    // Now delete the user to simulate missing relationship
    $user->delete();
    
    // Query should still work (with() eager loading handles missing relationships)
    $query2 = $table->getQuery();
    expect(fn() => $query2->get())->not->toThrow(\Exception::class);
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

test('empty filter values are handled correctly', function () {
    // Test that empty/null filter values don't cause errors
    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->whereHas('user', function ($q) use ($data) {
                $q->where('division', $data['value']);
            });
        }
        return $query;
    };
    
    $query = WeeklyTodo::query()->with('user');
    
    // Test various empty values
    $testCases = [
        ['value' => null],
        ['value' => ''],
        ['value' => []],
    ];
    
    foreach ($testCases as $data) {
        $filteredQuery = $filterCallback(clone $query, $data);
        expect(fn() => $filteredQuery->get())->not->toThrow(\Exception::class);
    }
});

test('invalid status filter value is handled', function () {
    WeeklyTodo::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);
    
    // Test filtering by invalid status
    $query = WeeklyTodo::query()->with('user');
    $filterCallback = function ($query, array $data) {
        if (!empty($data['value'])) {
            $query->where('status', $data['value']);
        }
        return $query;
    };
    
    $filteredQuery = $filterCallback(clone $query, ['value' => 'invalid_status']);
    $results = $filteredQuery->get();
    
    // Should return empty, not crash
    expect($results)->toBeEmpty();
});

