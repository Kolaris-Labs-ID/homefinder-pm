<?php

use App\Filament\Pages\TicketTimeline;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->status = TicketStatus::factory()->create(['project_id' => $this->project->id, 'sort_order' => 1]);

    $this->user = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->user->assignRole($role);
    auth()->login($this->user);
});

test('gantt data returns a sane structure for tickets with due dates', function () {
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'start_date' => '2026-07-01',
        'due_date' => '2026-07-10',
    ]);

    $component = Livewire::test(TicketTimeline::class, ['project_id' => $this->project->id]);
    $ganttData = $component->instance()->ganttData;

    expect($ganttData)->toHaveKeys(['data', 'links']);
    expect($ganttData['data'])->toHaveCount(1);

    $task = $ganttData['data'][0];
    expect($task)->toHaveKeys(['id', 'text', 'start_date', 'end_date', 'duration', 'progress', 'color', 'status']);
    expect($task['duration'])->toBeGreaterThan(0);
});

test('gantt data is empty when no project is selected', function () {
    $component = Livewire::test(TicketTimeline::class);

    expect($component->instance()->ganttData)->toBe(['data' => [], 'links' => []]);
});

test('gantt data ignores tickets without a due date', function () {
    Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status->id,
        'due_date' => null,
    ]);

    $component = Livewire::test(TicketTimeline::class, ['project_id' => $this->project->id]);

    expect($component->instance()->ganttData['data'])->toBe([]);
});
