<?php

use App\Exports\ProjectsExport;
use App\Exports\TicketsExport;
use App\Filament\Actions\ExportTicketsAction;
use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TicketResource\Pages\ListTickets;
use App\Helpers\RoleHelper;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-01 10:15:00'));
    Excel::fake();

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
    $this->actingAs($this->admin);
});

afterEach(function () {
    Carbon::setTestNow();
});

function exportedRowsFrom(string $fileName): array
{
    $rows = [];

    Excel::assertDownloaded($fileName, function ($export) use (&$rows) {
        foreach ($export->collection() as $record) {
            $rows[] = $export->map($record);
        }

        return true;
    });

    return $rows;
}

function exportedTicketRows(): array
{
    return exportedRowsFrom('tickets-2026-09-01-1015.xlsx');
}

function exportedProjectRows(): array
{
    return exportedRowsFrom('projects-2026-09-01-1015.xlsx');
}

function callExportAction($component, string $action, array $columns)
{
    // setActionData() dot-merges into the checkbox defaults, so set the array wholesale.
    return $component->mountAction($action)
        ->set('mountedActionsData.0.columns', $columns)
        ->callMountedAction();
}

function ticketColumnPickerOptions(): array
{
    $form = new ReflectionProperty(\Filament\Actions\Action::class, 'form');
    $form->setAccessible(true);

    return $form->getValue(ExportTicketsAction::make())[0]
        ->getChildComponents()[0]
        ->getOptions();
}

test('tickets export follows the active table filter', function () {
    $alpha = Project::factory()->create();
    $beta = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $alpha->id, 'name' => 'Alpha ticket']);
    Ticket::factory()->create(['project_id' => $beta->id, 'name' => 'Beta ticket']);

    callExportAction(
        Livewire::test(ListTickets::class)
            ->set('tableFilters.project_id.value', $alpha->id),
        'export_tickets',
        ['name']
    );

    expect(exportedTicketRows())->toBe([['Alpha ticket']]);
});

test('tickets export follows the table search term', function () {
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Needle in a haystack']);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Unrelated work']);

    callExportAction(
        Livewire::test(ListTickets::class)
            ->set('tableSearch', 'Needle'),
        'export_tickets',
        ['name']
    );

    expect(exportedTicketRows())->toBe([['Needle in a haystack']]);
});

test('tickets export covers every filtered row, not just the first page', function () {
    $project = Project::factory()->create();
    Ticket::factory()->count(15)->create(['project_id' => $project->id]);
    Ticket::factory()->count(3)->create();

    callExportAction(
        Livewire::test(ListTickets::class)
            ->set('tableFilters.project_id.value', $project->id),
        'export_tickets',
        ['name']
    );

    expect(exportedTicketRows())->toHaveCount(15);
});

test('projects export follows the active tab', function () {
    Project::factory()->create(['name' => 'Live project']);
    Project::factory()->create(['name' => 'Old project'])->update(['archived_at' => now()]);

    callExportAction(
        Livewire::test(ListProjects::class)
            ->set('activeTab', 'archived'),
        'export_projects',
        ['name']
    );

    expect(exportedProjectRows())->toBe([['Old project']]);
});

test('a non-member without elevated roles exports neither foreign tickets nor foreign projects', function () {
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Confidential']);

    $outsider = User::factory()->create();
    $outsider->givePermissionTo(
        Permission::firstOrCreate(['name' => 'view_any_ticket', 'guard_name' => 'web']),
        Permission::firstOrCreate(['name' => 'view_any_project', 'guard_name' => 'web']),
    );
    $this->actingAs($outsider);

    callExportAction(
        Livewire::test(ListTickets::class),
        'export_tickets',
        ['name']
    )
        ->assertNotified('Nothing to export');

    callExportAction(
        Livewire::test(ListProjects::class),
        'export_projects',
        ['name']
    )
        ->assertNotified('Nothing to export');
});

test('a formula-bearing ticket title is neutralised in the exported cell', function () {
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => '=1+1', 'created_at' => now()->subMinute()]);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => '@SUM(A1)']);

    callExportAction(
        Livewire::test(ListTickets::class)
            ->set('tableFilters.project_id.value', $project->id),
        'export_tickets',
        ['name']
    );

    // '@' never binds as a formula in a written .xlsx, so it is inert without a prefix.
    expect(exportedTicketRows())->toBe([['@SUM(A1)'], ["'=1+1"]]);
});

test('regression: ordinary titles that start with a CSV trigger are exported verbatim', function () {
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => '- fix login redirect']);

    callExportAction(
        Livewire::test(ListTickets::class)
            ->set('tableFilters.project_id.value', $project->id),
        'export_tickets',
        ['name']
    );

    expect(exportedTicketRows())->toBe([['- fix login redirect']]);
});

test('regression: an ownerless project exports a clean dash placeholder', function () {
    Project::factory()->create(['name' => 'Orphan'])->update(['created_by' => null]);

    callExportAction(
        Livewire::test(ListProjects::class),
        'export_projects',
        ['name', 'owner', 'division']
    );

    expect(exportedProjectRows())->toBe([['Orphan', '-', '-']]);
});

test('regression: an owner with a blank division exports the dash, not an empty cell', function () {
    $owner = User::factory()->create(['name' => 'Rina', 'division' => '']);
    Project::factory()->create(['name' => 'Blankdiv'])->update(['created_by' => $owner->id]);

    callExportAction(
        Livewire::test(ListProjects::class),
        'export_projects',
        ['name', 'owner', 'division']
    );

    expect(exportedProjectRows())->toBe([['Blankdiv', 'Rina', '-']]);
});

test('regression: the ticket column picker offers every column the export can write', function () {
    expect(ticketColumnPickerOptions())
        ->toBe((new TicketsExport(collect(), []))->getAvailableColumns())
        ->toHaveKey('start_date');
});

test('regression: the projects export query counts each relationship exactly once', function () {
    Project::factory()->create();

    $selects = [];
    DB::listen(function ($query) use (&$selects) {
        if (str_contains($query->sql, 'members_count')) {
            $selects[] = $query->sql;
        }
    });

    callExportAction(
        Livewire::test(ListProjects::class),
        'export_projects',
        ['name', 'members_count', 'tickets_count']
    );

    expect($selects)->not->toBeEmpty();
    foreach ($selects as $sql) {
        expect(substr_count($sql, 'as "members_count"'))->toBe(1)
            ->and(substr_count($sql, 'as "tickets_count"'))->toBe(1);
    }
});

test('regression: zero counts export as a real zero rather than a skipped cell', function () {
    Project::factory()->create(['name' => 'Empty']);

    callExportAction(
        Livewire::test(ListProjects::class),
        'export_projects',
        ['name', 'members_count', 'tickets_count']
    );

    expect(exportedProjectRows())->toBe([['Empty', 0, 0]])
        ->and(new ProjectsExport(collect(), []))->toBeInstanceOf(WithStrictNullComparison::class);
});

test('regression: the project board export streams a download instead of publishing a file', function () {
    $project = Project::factory()->create(['name' => 'Board Project']);
    $status = TicketStatus::factory()->create(['project_id' => $project->id, 'sort_order' => 1]);
    Ticket::factory()->create(['project_id' => $project->id, 'ticket_status_id' => $status->id, 'name' => 'Board ticket']);

    callExportAction(
        Livewire::test(ProjectBoard::class, ['project_id' => $project->id]),
        'export_tickets',
        ['name']
    );

    expect(exportedRowsFrom('tickets_board_project_2026_09_01_10_15_00.xlsx'))->toBe([['Board ticket']]);
});

function seededRoleUser(string $role): User
{
    (new RoleSeeder)->run();

    return tap(User::factory()->create())->assignRole($role);
}

function exportedTicketNames(): array
{
    return collect(exportedTicketRows())->flatten()->sort()->values()->all();
}

function exportedProjectNames(): array
{
    return collect(exportedProjectRows())->flatten()->sort()->values()->all();
}

test('an employee sees the export button on the Tickets list', function () {
    $this->actingAs(seededRoleUser('member'));

    Livewire::test(ListTickets::class)->assertActionVisible('export_tickets');
});

test('an employee exports the tickets they are assigned to', function () {
    $employee = seededRoleUser('member');
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Assigned to me'])
        ->assignees()->attach($employee->id);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Assigned to nobody I know']);

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Assigned to me']);
});

test('an employee exports the tickets they created even when nobody assigned them', function () {
    $employee = seededRoleUser('member');
    $project = Project::factory()->create();
    Ticket::factory()->create([
        'project_id' => $project->id,
        'created_by' => $employee->id,
        'name' => 'Filed by me',
    ]);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Filed by someone else']);

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Filed by me']);
});

test('an employee exports every ticket in a project they are a member of, even ones that are not theirs', function () {
    $employee = seededRoleUser('member');
    $project = Project::factory()->create();
    $project->members()->attach($employee->id);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Teammate work']);

    $foreign = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $foreign->id, 'name' => 'Other project work']);

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Teammate work']);
});

test('an employee export excludes another employee unrelated tickets', function () {
    $employee = seededRoleUser('member');
    $mine = Project::factory()->create();
    $mine->members()->attach($employee->id);
    Ticket::factory()->create(['project_id' => $mine->id, 'name' => 'Mine']);

    $colleague = seededRoleUser('member');
    $theirs = Project::factory()->create();
    $theirs->members()->attach($colleague->id);
    Ticket::factory()->create(['project_id' => $theirs->id, 'name' => 'Theirs'])
        ->assignees()->attach($colleague->id);

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Mine']);
});

test('an employee filtering on a project they do not belong to exports nothing', function () {
    $employee = seededRoleUser('member');
    $mine = Project::factory()->create();
    $mine->members()->attach($employee->id);
    Ticket::factory()->create(['project_id' => $mine->id, 'name' => 'Mine']);

    $foreign = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $foreign->id, 'name' => 'Theirs']);

    $this->actingAs($employee);
    callExportAction(
        Livewire::test(ListTickets::class)->set('tableFilters.project_id.value', $foreign->id),
        'export_tickets',
        ['name']
    )->assertNotified('Nothing to export');
});

test('a user with no role at all never reaches the export button', function () {
    $this->actingAs(User::factory()->create());

    expect(TicketResource::canViewAny())->toBeFalse();

    // The button is unreachable because the whole page is: User::canAccessPanel()
    // demands at least one role, so /admin/tickets 403s before any action renders.
    $this->get('/admin/tickets')->assertForbidden();
    expect(Livewire::test(ListTickets::class)->instance())->toBeNull();
});

test('a super admin exports every employee tickets across projects they do not belong to', function () {
    $projects = Project::factory()->count(3)->create();
    $names = ['Ticket by Ana', 'Ticket by Budi', 'Ticket by Citra'];

    foreach ($projects as $index => $project) {
        $owner = seededRoleUser('member');
        $project->members()->attach($owner->id);
        Ticket::factory()->create(['project_id' => $project->id, 'name' => $names[$index]])
            ->assignees()->attach($owner->id);
    }

    expect($this->admin->projects()->count())->toBe(0);

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe($names);
});

test('a super admin export still honours the active table filter', function () {
    $alpha = Project::factory()->create();
    $beta = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $alpha->id, 'name' => 'Alpha only']);
    Ticket::factory()->create(['project_id' => $beta->id, 'name' => 'Beta only']);

    callExportAction(
        Livewire::test(ListTickets::class)->set('tableFilters.project_id.value', $beta->id),
        'export_tickets',
        ['name']
    );

    expect(exportedTicketNames())->toBe(['Beta only']);
});

test('the admin role holds every permission yet is still scoped to its own tickets, because the wide-scope check reads roles not permissions', function () {
    $adminRoleUser = seededRoleUser('admin');
    $project = Project::factory()->create();
    $project->members()->attach($adminRoleUser->id);
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Within my project']);
    Ticket::factory()->create(['name' => 'Somebody else project']);

    $this->actingAs($adminRoleUser);

    expect($adminRoleUser->can('view_any_ticket'))->toBeTrue()
        ->and(RoleHelper::canCommentOnAnyTicket($adminRoleUser))->toBeFalse();

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Within my project']);
});

test('an employee exports only the projects they are a member of', function () {
    $employee = seededRoleUser('member');
    Project::factory()->create(['name' => 'My project'])->members()->attach($employee->id);
    Project::factory()->create(['name' => 'Not my project']);
    Project::factory()->create(['name' => 'Also not my project']);

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListProjects::class), 'export_projects', ['name']);

    expect(exportedProjectNames())->toBe(['My project']);
});

test('a super admin exports all projects, including those they do not belong to', function () {
    Project::factory()->create(['name' => 'Ana project'])->members()->attach(seededRoleUser('member')->id);
    Project::factory()->create(['name' => 'Budi project'])->members()->attach(seededRoleUser('member')->id);
    Project::factory()->create(['name' => 'Citra project']);

    callExportAction(Livewire::test(ListProjects::class), 'export_projects', ['name']);

    expect(exportedProjectNames())->toBe(['Ana project', 'Budi project', 'Citra project']);
});

function exportedHeadingsFrom(string $fileName): array
{
    $headings = [];

    Excel::assertDownloaded($fileName, function ($export) use (&$headings) {
        $headings = $export->headings();

        return true;
    });

    return $headings;
}

function executiveUser(string $role): User
{
    (new RoleSeeder)->run();

    $executive = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $executive->syncPermissions(Role::findByName('member', 'web')->permissions);

    return tap(User::factory()->create())->assignRole($executive);
}

test('an employee export leaves out their own tickets once the project is closed', function () {
    $employee = seededRoleUser('member');

    $live = Project::factory()->create();
    $live->members()->attach($employee->id);
    Ticket::factory()->create(['project_id' => $live->id, 'name' => 'Live ticket']);

    $closed = Project::factory()->create();
    $closed->members()->attach($employee->id);
    $stranded = Ticket::factory()->create(['project_id' => $closed->id, 'name' => 'Closed project ticket']);
    $stranded->assignees()->attach($employee->id);

    // Closing a project through the model auto-archives its tickets, which would hide
    // them by ticket state alone. Close it underneath the observer so the export has to
    // exclude the ticket on the project's state, the way legacy closed projects do.
    DB::table('projects')->where('id', $closed->id)->update(['done_at' => now()->subDay()]);

    expect($stranded->fresh()->archived_at)->toBeNull();

    $this->actingAs($employee);
    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Live ticket']);
});

test('a super admin export leaves out tickets in archived projects as well', function () {
    $live = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $live->id, 'name' => 'Live ticket']);

    $archived = Project::factory()->create();
    $stranded = Ticket::factory()->create(['project_id' => $archived->id, 'name' => 'Archived project ticket']);
    DB::table('projects')->where('id', $archived->id)->update(['archived_at' => now()->subDay()]);

    expect($stranded->fresh()->archived_at)->toBeNull();

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Live ticket']);
});

test('a CEO or COO exports tickets from projects they never joined', function (string $role) {
    $executive = executiveUser($role);
    $foreign = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $foreign->id, 'name' => 'Somebody else work']);

    $this->actingAs($executive);

    expect(RoleHelper::canCommentOnAnyTicket($executive))->toBeTrue();

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Somebody else work']);
})->with(['CEO', 'COO']);

test('a CTO or HR exports every project yet only their own tickets, because the two scope checks name different roles', function (string $role) {
    $executive = executiveUser($role);

    $mine = Project::factory()->create(['name' => 'My project']);
    $mine->members()->attach($executive->id);
    Ticket::factory()->create([
        'project_id' => $mine->id,
        'ticket_status_id' => TicketStatus::factory()->create(['project_id' => $mine->id])->id,
        'name' => 'Mine',
    ]);

    $foreign = Project::factory()->create(['name' => 'Not my project']);
    Ticket::factory()->create([
        'project_id' => $foreign->id,
        'ticket_status_id' => TicketStatus::factory()->create(['project_id' => $foreign->id])->id,
        'name' => 'Theirs',
    ]);

    $this->actingAs($executive);

    expect(RoleHelper::canCommentOnAnyTicket($executive))->toBeFalse();

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['name']);
    expect(exportedTicketNames())->toBe(['Mine']);

    callExportAction(Livewire::test(ListProjects::class), 'export_projects', ['name']);
    expect(exportedProjectNames())->toBe(['My project', 'Not my project']);
})->with(['CTO', 'HR']);

test('an employee cannot export the tickets of a Kanban board they were never added to', function () {
    $employee = seededRoleUser('member');
    $foreign = Project::factory()->create(['name' => 'Secret Project']);
    $status = TicketStatus::factory()->create(['project_id' => $foreign->id, 'sort_order' => 1]);
    Ticket::factory()->create([
        'project_id' => $foreign->id,
        'ticket_status_id' => $status->id,
        'name' => 'Payroll rework',
    ]);

    $this->actingAs($employee);
    $board = Livewire::test(ProjectBoard::class, ['project_id' => $foreign->id]);

    expect($board->get('selectedProject'))->toBeNull();

    // The header action's ->visible() closure hides the button but never gates the
    // public Livewire method, so the denial has to come from the method itself.
    $board->call('exportTickets', ['name'])->assertNotified('Export Failed');
});

test('an employee cannot switch a Kanban board onto a foreign project id', function () {
    $employee = seededRoleUser('member');
    $mine = Project::factory()->create(['name' => 'My Project']);
    $mine->members()->attach($employee->id);
    $foreign = Project::factory()->create(['name' => 'Secret Project']);

    $this->actingAs($employee);

    expect(Livewire::test(ProjectBoard::class)->call('selectProject', $foreign->id)->get('selectedProject'))
        ->toBeNull()
        ->and(Livewire::test(ProjectBoard::class)->call('selectProject', $mine->id)->get('selectedProject')?->name)
        ->toBe('My Project');
});

test('an employee never sees a foreign Kanban board even by typing its url', function () {
    $employee = seededRoleUser('member');
    $foreign = Project::factory()->create(['name' => 'Secret Project']);
    $status = TicketStatus::factory()->create(['project_id' => $foreign->id, 'sort_order' => 1]);
    Ticket::factory()->create([
        'project_id' => $foreign->id,
        'ticket_status_id' => $status->id,
        'name' => 'Payroll rework',
    ]);

    $this->actingAs($employee);

    $this->get(ProjectBoard::getUrl(['project_id' => $foreign->id]))
        ->assertOk()
        ->assertDontSee('Payroll rework');
});

test('a project member exports that board tickets', function () {
    $employee = seededRoleUser('member');
    $project = Project::factory()->create(['name' => 'Board Project']);
    $project->members()->attach($employee->id);
    $status = TicketStatus::factory()->create(['project_id' => $project->id, 'sort_order' => 1]);
    Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'name' => 'Board ticket',
    ]);

    $this->actingAs($employee);
    Livewire::test(ProjectBoard::class, ['project_id' => $project->id])->call('exportTickets', ['name']);

    expect(exportedRowsFrom('tickets_board_project_2026_09_01_10_15_00.xlsx'))->toBe([['Board ticket']]);
});

test('the board export drops archived tickets, exactly like the Tickets list export', function () {
    $project = Project::factory()->create(['name' => 'Board Project']);
    $status = TicketStatus::factory()->create(['project_id' => $project->id, 'sort_order' => 1]);
    Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'name' => 'Live ticket',
    ]);
    Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'name' => 'Retired ticket',
    ])->update(['archived_at' => now()]);

    Livewire::test(ProjectBoard::class, ['project_id' => $project->id])->call('exportTickets', ['name']);

    expect(exportedRowsFrom('tickets_board_project_2026_09_01_10_15_00.xlsx'))->toBe([['Live ticket']]);
});

test('the exported ticket headings name exactly the columns the exporter picked', function () {
    $project = Project::factory()->create();
    Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Only ticket']);

    callExportAction(Livewire::test(ListTickets::class), 'export_tickets', ['uuid', 'name', 'due_date']);

    expect(exportedHeadingsFrom('tickets-2026-09-01-1015.xlsx'))
        ->toBe(['Ticket ID', 'Title', 'Due Date'])
        ->toHaveCount(count(exportedTicketRows()[0]));
});

test('the exported project headings name exactly the columns the exporter picked', function () {
    Project::factory()->create(['name' => 'Solo']);

    callExportAction(Livewire::test(ListProjects::class), 'export_projects', ['name', 'members_count']);

    expect(exportedHeadingsFrom('projects-2026-09-01-1015.xlsx'))
        ->toBe(['Project Name', 'Members'])
        ->toHaveCount(count(exportedProjectRows()[0]));
});

test('regression: a column neither export can write is dropped from the headings and the data alike', function () {
    $project = Project::factory()->create(['name' => 'Solo']);
    $ticket = Ticket::factory()->create(['project_id' => $project->id, 'name' => 'Only ticket']);

    $tickets = new TicketsExport(collect(), ['name', 'not_a_column', 'due_date']);
    $projects = new ProjectsExport(collect(), ['name', 'not_a_column']);

    expect($tickets->headings())->toBe(['Title', 'Due Date'])
        ->and($tickets->map($ticket))->toHaveCount(2)
        ->and($projects->headings())->toBe(['Project Name'])
        ->and($projects->map($project))->toHaveCount(1);
});

test('picking a project immediately offers that project statuses for narrowing the export', function () {
    $project = Project::factory()->create();
    $todo = TicketStatus::factory()->create(['project_id' => $project->id, 'name' => 'To Do', 'sort_order' => 1]);
    $done = TicketStatus::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'sort_order' => 2]);
    Ticket::factory()->create(['project_id' => $project->id, 'ticket_status_id' => $todo->id, 'name' => 'Open work']);
    Ticket::factory()->create(['project_id' => $project->id, 'ticket_status_id' => $done->id, 'name' => 'Finished work']);

    $component = Livewire::test(ListTickets::class)->set('tableFilters.project_id.value', $project->id);

    expect($component->instance()->getTable()->getFilter('ticket_status_id')->getOptions())
        ->toEqualCanonicalizing([$todo->id => 'To Do', $done->id => 'Done']);

    callExportAction($component->set('tableFilters.ticket_status_id.value', $todo->id), 'export_tickets', ['name']);

    expect(exportedTicketNames())->toBe(['Open work']);
});
