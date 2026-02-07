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

test('projects are loaded with ticket counts for super admin', function () {
    // Create multiple projects with different ticket counts
    $project1 = Project::factory()->create();
    $project2 = Project::factory()->create();
    
    Ticket::factory()->count(3)->create(['project_id' => $project1->id]);
    Ticket::factory()->count(5)->create(['project_id' => $project2->id]);
    
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    $projects = $component->get('projects');
    
    // Check that projects are loaded with tickets_count
    expect($projects)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($projects->count())->toBeGreaterThanOrEqual(2);
    
    // Find our test projects in the collection
    $loadedProject1 = $projects->firstWhere('id', $project1->id);
    $loadedProject2 = $projects->firstWhere('id', $project2->id);
    
    expect($loadedProject1)->not->toBeNull();
    expect($loadedProject2)->not->toBeNull();
    expect($loadedProject1->tickets_count)->toBe(3);
    expect($loadedProject2->tickets_count)->toBe(5);
    
    // Users relation for avatars should be eager loaded
    expect($loadedProject1->relationLoaded('users'))->toBeTrue();
    expect($loadedProject2->relationLoaded('users'))->toBeTrue();
});

test('regular user sees only their assigned projects', function () {
    $regularUser = User::factory()->create();
    $otherUser = User::factory()->create();
    
    // Create projects
    $userProject = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $sharedProject = Project::factory()->create();
    
    // Assign projects to users
    $regularUser->projects()->attach([$userProject->id, $sharedProject->id]);
    $otherUser->projects()->attach([$otherProject->id, $sharedProject->id]);
    
    auth()->login($regularUser);
    
    $component = Livewire::test(ProjectBoard::class);
    
    $projects = $component->get('projects');
    $projectIds = $projects->pluck('id')->toArray();
    
    // Regular user should see their projects
    expect($projectIds)->toContain($userProject->id);
    expect($projectIds)->toContain($sharedProject->id);
    // But not other user's project
    expect($projectIds)->not->toContain($otherProject->id);
});

test('project cards display when no project is selected', function () {
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    
    // When no project is selected, selectedProject should be null
    expect($component->get('selectedProject'))->toBeNull();
    
    // Projects should be loaded
    expect($component->get('projects'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    
    // View should render without errors
    $component->assertSuccessful();
});

test('can select project via selectProject method', function () {
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    
    // Initially no project selected
    expect($component->get('selectedProject'))->toBeNull();
    
    // Select a project
    $component->call('selectProject', $this->project->id);
    
    // Project should now be selected
    expect($component->get('selectedProject'))->not->toBeNull();
    expect($component->get('selectedProject')->id)->toBe($this->project->id);
    expect($component->get('selectedProjectId'))->toBe($this->project->id);
});

test('empty state displays when user has no projects', function () {
    $userWithoutProjects = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $userWithoutProjects->assignRole($role);
    
    auth()->login($userWithoutProjects);
    
    $component = Livewire::test(ProjectBoard::class);
    
    // Projects collection should be empty
    expect($component->get('projects'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->get('projects')->isEmpty())->toBeTrue();
    
    // View should still render successfully
    $component->assertSuccessful();
});

test('project cards show correct ticket counts', function () {
    $projectWithTickets = Project::factory()->create();
    $projectWithoutTickets = Project::factory()->create();
    
    Ticket::factory()->count(7)->create(['project_id' => $projectWithTickets->id]);
    
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    
    $projects = $component->get('projects');
    
    $loadedProjectWithTickets = $projects->firstWhere('id', $projectWithTickets->id);
    $loadedProjectWithoutTickets = $projects->firstWhere('id', $projectWithoutTickets->id);
    
    expect($loadedProjectWithTickets->tickets_count)->toBe(7);
    expect($loadedProjectWithoutTickets->tickets_count)->toBe(0);
});

test('selecting project loads ticket statuses and users', function () {
    // Create tickets with assignees
    $assignee1 = User::factory()->create();
    $assignee2 = User::factory()->create();
    
    $ticket1 = Ticket::factory()->create([
        'project_id' => $this->project->id,
        'ticket_status_id' => $this->status1->id,
    ]);
    $ticket1->assignees()->attach([$assignee1->id, $assignee2->id]);
    
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    
    // Select the project
    $component->call('selectProject', $this->project->id);
    
    // Ticket statuses should be loaded
    expect($component->get('ticketStatuses'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->get('ticketStatuses')->isNotEmpty())->toBeTrue();
    
    // Project users should be loaded (users assigned to tickets)
    expect($component->get('projectUsers'))->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($component->get('projectUsers')->count())->toBeGreaterThanOrEqual(2);
    
    $projectUserIds = $component->get('projectUsers')->pluck('id')->toArray();
    expect($projectUserIds)->toContain($assignee1->id);
    expect($projectUserIds)->toContain($assignee2->id);
});

test('updatedSelectedProjectId clears selection when value is empty', function () {
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class, ['project_id' => $this->project->id]);
    
    // Initially project should be selected
    expect($component->get('selectedProject'))->not->toBeNull();
    
    // Clear selection by setting selectedProjectId to null
    // Livewire will automatically call updatedSelectedProjectId hook
    $component->set('selectedProjectId', null);
    
    // Project should be cleared
    expect($component->get('selectedProject'))->toBeNull();
    expect($component->get('ticketStatuses')->isEmpty())->toBeTrue();
    expect($component->get('projectUsers')->isEmpty())->toBeTrue();
});

test('super admin sees all projects regardless of membership', function () {
    $otherProject = Project::factory()->create();
    
    // Ensure super_admin user is not a member of otherProject
    expect($this->user->projects()->where('projects.id', $otherProject->id)->exists())->toBeFalse();
    
    auth()->login($this->user);
    
    $component = Livewire::test(ProjectBoard::class);
    
    $projectIds = $component->get('projects')->pluck('id')->toArray();
    
    // Super admin should see all projects
    expect($projectIds)->toContain($this->project->id);
    expect($projectIds)->toContain($otherProject->id);
});

// =========================================================
// Project Board Table View Tests
// =========================================================

test('getProjectStatus returns Done for done projects', function () {
    $project = Project::factory()->done()->create();

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $status = $page->getProjectStatus($project);

    expect($status['label'])->toBe('Done');
    expect($status['bg'])->toBe('#7AD7A2');
    expect($status['text'])->toBe('#064E3B');
});

test('getProjectStatus returns Stuck for overdue projects', function () {
    $project = Project::factory()->create([
        'end_date' => now()->subDays(5),
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $status = $page->getProjectStatus($project);

    expect($status['label'])->toBe('Stuck');
    expect($status['bg'])->toBe('#FC7D82');
});

test('getProjectStatus returns Waiting on it for active projects', function () {
    $project = Project::factory()->create([
        'end_date' => now()->addDays(30),
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $status = $page->getProjectStatus($project);

    expect($status['label'])->toBe('Waiting on it');
    expect($status['bg'])->toBe('#F4C571');
});

test('getDueDateIndicator returns green for on-track projects', function () {
    $project = Project::factory()->create([
        'end_date' => now()->addDays(30),
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $indicator = $page->getDueDateIndicator($project);

    expect($indicator['dot'])->toBe('#10B981');
    expect($indicator['icon'])->toBe('success');
});

test('getDueDateIndicator returns red for overdue projects', function () {
    $project = Project::factory()->create([
        'end_date' => now()->subDays(5),
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $indicator = $page->getDueDateIndicator($project);

    expect($indicator['dot'])->toBe('#EF4444');
    expect($indicator['icon'])->toBe('danger');
});

test('getDueDateIndicator returns amber for near-deadline projects', function () {
    $project = Project::factory()->create([
        'end_date' => now()->addDays(3),
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $indicator = $page->getDueDateIndicator($project);

    expect($indicator['dot'])->toBe('#D97706');
    expect($indicator['icon'])->toBe('warning');
});

test('getDueDateIndicator returns gray for projects without end date', function () {
    $project = Project::factory()->withoutEndDate()->create();

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $page = $component->instance();

    $indicator = $page->getDueDateIndicator($project);

    expect($indicator['dot'])->toBe('#9CA3AF');
    expect($indicator['icon'])->toBe('none');
});

test('search filters projects by name', function () {
    Project::factory()->create(['name' => 'Alpha Design Project']);
    Project::factory()->create(['name' => 'Beta Marketing App']);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Set search to filter
    $component->set('projectSearch', 'Alpha');

    $grouped = $component->instance()->getGroupedProjects();
    $allProjects = $grouped->flatten();

    expect($allProjects->pluck('name')->toArray())->toContain('Alpha Design Project');
    expect($allProjects->pluck('name')->toArray())->not->toContain('Beta Marketing App');
});

test('search filters projects by ticket prefix', function () {
    Project::factory()->create(['name' => 'Some Project', 'ticket_prefix' => 'XYZQ']);
    Project::factory()->create(['name' => 'Another Project', 'ticket_prefix' => 'ABCD']);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $component->set('projectSearch', 'XYZQ');

    $grouped = $component->instance()->getGroupedProjects();
    $allProjects = $grouped->flatten();

    expect($allProjects->count())->toBe(1);
    expect($allProjects->first()->ticket_prefix)->toBe('XYZQ');
});

test('sortProjectsBy toggles direction on same field', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Default sort is name asc
    expect($component->get('projectSortField'))->toBe('name');
    expect($component->get('projectSortDirection'))->toBe('asc');

    // Click name again toggles to desc
    $component->call('sortProjectsBy', 'name');
    expect($component->get('projectSortDirection'))->toBe('desc');

    // Click name again toggles back to asc
    $component->call('sortProjectsBy', 'name');
    expect($component->get('projectSortDirection'))->toBe('asc');
});

test('sortProjectsBy switches to new field with asc direction', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    $component->call('sortProjectsBy', 'end_date');
    expect($component->get('projectSortField'))->toBe('end_date');
    expect($component->get('projectSortDirection'))->toBe('asc');
});

test('toggleGroup collapses and expands groups', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Initially no collapsed groups
    expect($component->get('collapsedGroups'))->toBe([]);

    // Collapse a group
    $component->call('toggleGroup', 'January 2026');
    expect($component->get('collapsedGroups'))->toContain('January 2026');

    // Expand the same group
    $component->call('toggleGroup', 'January 2026');
    expect($component->get('collapsedGroups'))->not->toContain('January 2026');
});

test('toggleColumn hides and shows columns', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Initially no hidden columns
    expect($component->get('hiddenColumns'))->toBe([]);

    // Hide a column
    $component->call('toggleColumn', 'due_date');
    expect($component->get('hiddenColumns'))->toContain('due_date');

    // Show it again
    $component->call('toggleColumn', 'due_date');
    expect($component->get('hiddenColumns'))->not->toContain('due_date');
});

test('getGroupedProjects groups by end_date month', function () {
    // Create projects with specific end dates
    Project::factory()->create([
        'name' => 'March Project',
        'end_date' => '2026-03-15',
    ]);
    Project::factory()->create([
        'name' => 'April Project',
        'end_date' => '2026-04-10',
    ]);
    Project::factory()->create([
        'name' => 'No Date Project',
        'end_date' => null,
    ]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $grouped = $component->instance()->getGroupedProjects();

    // Should have groups for March 2026, April 2026, and No Due Date
    expect($grouped->keys()->toArray())->toContain('March 2026');
    expect($grouped->keys()->toArray())->toContain('April 2026');
    expect($grouped->keys()->toArray())->toContain('No Due Date');
});

test('project table view renders successfully with grouped projects', function () {
    Project::factory()->create(['end_date' => now()->addDays(10)]);
    Project::factory()->create(['end_date' => now()->addMonth()]);

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Page should render without errors
    $component->assertSuccessful();

    // Should see the table headers
    $component->assertSee('Project');
    $component->assertSee('Due Date');
    $component->assertSee('Status');
    $component->assertSee('Members');
});

test('project table view shows search input and sort tabs', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    // Should see the search placeholder and sort tabs
    $component->assertSee('Search');
    $component->assertSee('Name');
    $component->assertSee('Due Date');
    $component->assertSee('Status');
    $component->assertSee('Division');
});

test('getProjectDivision returns most common division from members', function () {
    $project = Project::factory()->create();

    $user1 = User::factory()->create(['division' => 'Engineering']);
    $user2 = User::factory()->create(['division' => 'Engineering']);
    $user3 = User::factory()->create(['division' => 'Marketing']);

    $project->users()->attach([$user1->id, $user2->id, $user3->id]);
    $project->load('users');

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $division = $component->instance()->getProjectDivision($project);

    expect($division)->toBe('Engineering');
});

test('getProjectDivision returns No Division when project has no members', function () {
    $project = Project::factory()->create();

    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);
    $division = $component->instance()->getProjectDivision($project);

    expect($division)->toBe('No Division');
});

test('sortProjectsBy division sorts projects by member division', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    $component->call('sortProjectsBy', 'division');

    expect($component->get('projectSortField'))->toBe('division');
    expect($component->get('projectSortDirection'))->toBe('asc');

    // Click again to toggle direction
    $component->call('sortProjectsBy', 'division');
    expect($component->get('projectSortDirection'))->toBe('desc');
});

test('collapsedGroups and hiddenColumns are initialized on mount', function () {
    auth()->login($this->user);

    $component = Livewire::test(ProjectBoard::class);

    expect($component->get('collapsedGroups'))->toBe([]);
    expect($component->get('hiddenColumns'))->toBe([]);
    expect($component->get('projectSearch'))->toBe('');
    expect($component->get('projectSortField'))->toBe('name');
    expect($component->get('projectSortDirection'))->toBe('asc');
});

