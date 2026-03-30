<?php

use App\Filament\Widgets\EmployeeTicketsWidget;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole($this->role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->priority = TicketPriority::factory()->create();

    expect($this->viewer->hasRole('super_admin'))->toBeTrue();
});

test('employee tickets user query includes creators and scoped member-project counts', function () {
    $statusAttrs = ['name' => 'WidgetTest Status', 'is_completed' => false];

    // 1) Creator only, not on project_members → row appears, scoped count 0
    $gloria = User::factory()->create(['name' => 'Gloria Test']);
    $projectA = Project::factory()->create();
    $statusA = TicketStatus::factory()->create(array_merge($statusAttrs, ['project_id' => $projectA->id]));
    Ticket::factory()->create([
        'project_id' => $projectA->id,
        'ticket_status_id' => $statusA->id,
        'priority_id' => $this->priority->id,
        'created_by' => $gloria->id,
        'archived_at' => null,
    ]);

    $rowGloria = EmployeeTicketsWidget::employeeTicketsUserQuery($this->viewer)
        ->where('users.id', $gloria->id)
        ->first();
    expect($rowGloria)->not->toBeNull()
        ->and((int) $rowGloria->member_projects_tickets_count)->toBe(0);

    // 2) Creator on member project → scoped count 1
    $userB = User::factory()->create();
    $projectB = Project::factory()->create();
    $userB->projects()->attach($projectB->id);
    $statusB = TicketStatus::factory()->create(array_merge($statusAttrs, ['project_id' => $projectB->id]));
    Ticket::factory()->create([
        'project_id' => $projectB->id,
        'ticket_status_id' => $statusB->id,
        'priority_id' => $this->priority->id,
        'created_by' => $userB->id,
        'archived_at' => null,
    ]);

    $rowB = EmployeeTicketsWidget::employeeTicketsUserQuery($this->viewer)
        ->where('users.id', $userB->id)
        ->first();
    expect($rowB)->not->toBeNull()
        ->and((int) $rowB->member_projects_tickets_count)->toBe(1);

    // 3) Creator + assignee same ticket → distinct count still 1
    $userC = User::factory()->create();
    $projectC = Project::factory()->create();
    $userC->projects()->attach($projectC->id);
    $statusC = TicketStatus::factory()->create(array_merge($statusAttrs, ['project_id' => $projectC->id]));
    $ticketC = Ticket::factory()->create([
        'project_id' => $projectC->id,
        'ticket_status_id' => $statusC->id,
        'priority_id' => $this->priority->id,
        'created_by' => $userC->id,
        'archived_at' => null,
    ]);
    $ticketC->assignees()->attach($userC->id);

    $rowC = EmployeeTicketsWidget::employeeTicketsUserQuery($this->viewer)
        ->where('users.id', $userC->id)
        ->first();
    expect($rowC)->not->toBeNull()
        ->and((int) $rowC->member_projects_tickets_count)->toBe(1);
});
