<?php

/**
 * Integration Tests for Recurring Tickets End-to-End Flow
 *
 * Tests the complete flow from template creation to ticket generation.
 * Requirements: 2.2, 2.3, 2.4, 2.5, 2.6
 */

use App\Enums\TicketFrequency;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateGeneration;
use App\Models\User;
use App\Services\RecurringTicketService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Clean up before each test
    TicketTemplateGeneration::query()->delete();
    TicketTemplate::query()->delete();
    Ticket::query()->delete();

    // Create super_admin role with all permissions
    $this->role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // Create ticket template permissions (using Shield format)
    $permissions = [
        'view_any_ticket::template',
        'view_ticket::template',
        'create_ticket::template',
        'update_ticket::template',
        'delete_ticket::template',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->role->syncPermissions($permissions);

    // Create user with super_admin role
    $this->user = User::factory()->create();
    $this->user->assignRole($this->role);
    $this->actingAs($this->user);

    // Create a project with status and members
    $this->project = Project::factory()->create(['name' => 'HR Department']);
    $this->project->members()->attach($this->user->id);

    $this->status = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'To Do',
        'sort_order' => 1,
    ]);

    $this->priority = TicketPriority::firstOrCreate(
        ['name' => 'High'],
        ['color' => '#ff0000']
    );
});

/**
 * Test: End-to-end flow - Create template, run command, verify ticket on Kanban
 * Requirements: 2.2, 2.3, 2.4, 2.5, 2.6
 */
test('end-to-end: template creation to ticket generation', function () {
    $today = Carbon::today();

    // Step 1: Create a ticket template (simulating Filament resource creation)
    $template = TicketTemplate::create([
        'name' => 'Monthly Payroll Processing',
        'description' => '<p>Process monthly payroll for all employees</p>',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => $today->day, // Set to today so it generates
        'lead_time_days' => 0,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Attach assignees
    $template->assignees()->attach($this->user->id);

    // Verify template was created correctly
    expect($template)->toBeInstanceOf(TicketTemplate::class);
    expect($template->name)->toBe('Monthly Payroll Processing');
    expect($template->is_active)->toBeTrue();

    // Step 2: Run the generation command
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated: 1 ticket(s)');

    // Step 3: Verify ticket was created with correct data
    $ticket = Ticket::where('name', 'Monthly Payroll Processing')->first();

    expect($ticket)->not->toBeNull();

    // Verify ticket data matches template (Requirements 2.3, 2.4, 2.5, 2.6)
    expect($ticket->name)->toBe($template->name);
    expect($ticket->description)->toBe($template->description);
    expect($ticket->project_id)->toBe($template->project_id);
    expect($ticket->priority_id)->toBe($template->priority_id);
    expect($ticket->ticket_status_id)->toBe($template->initial_status_id);
    expect($ticket->due_date->toDateString())->toBe($today->toDateString());

    // Verify assignees were copied
    expect($ticket->assignees)->toHaveCount(1);
    expect($ticket->assignees->first()->id)->toBe($this->user->id);

    // Verify generation record was created
    $generation = TicketTemplateGeneration::where('ticket_template_id', $template->id)->first();
    expect($generation)->not->toBeNull();
    expect($generation->ticket_id)->toBe($ticket->id);
    expect($generation->target_date->toDateString())->toBe($today->toDateString());

    // Verify ticket appears in project (Kanban board)
    $projectTickets = $this->project->tickets()->where('name', 'Monthly Payroll Processing')->get();
    expect($projectTickets)->toHaveCount(1);
})->group('recurring-tickets', 'integration');


/**
 * Test: Weekly template generates ticket on correct day
 * Requirements: 2.2, 3.3
 */
test('weekly template generates ticket on target day', function () {
    $today = Carbon::today();

    // Create weekly template for today's day of week
    $template = TicketTemplate::create([
        'name' => 'Weekly Status Report',
        'description' => 'Submit weekly status report',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => $today->dayOfWeekIso,
        'lead_time_days' => 0,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Run generation
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated: 1 ticket(s)');

    // Verify ticket was created
    $ticket = Ticket::where('name', 'Weekly Status Report')->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->due_date->dayOfWeekIso)->toBe($today->dayOfWeekIso);
})->group('recurring-tickets', 'integration');


/**
 * Test: Lead time creates ticket before due date
 * Requirements: 2.2
 */
test('lead time creates ticket before due date', function () {
    $today = Carbon::today();
    $leadTimeDays = 5;

    // Use the service to calculate the actual target date that will be used
    $service = app(RecurringTicketService::class);

    // Calculate target day (5 days from now)
    $targetDate = $today->copy()->addDays($leadTimeDays);

    // Get the actual target date the service will calculate
    // This handles edge cases like month boundaries
    $actualTargetDate = $service->calculateMonthlyTargetDate($targetDate->day, $today);

    // Only run this test if the target date is exactly lead_time_days away
    // (i.e., the ticket should generate today)
    $generationDate = $actualTargetDate->copy()->subDays($leadTimeDays);

    if (!$today->isSameDay($generationDate)) {
        // Skip this test iteration - the date math doesn't align for generation today
        // This can happen at month boundaries
        $this->markTestSkipped('Target date calculation does not align with lead time for today');
    }

    // Create template with lead time
    $template = TicketTemplate::create([
        'name' => 'Tax Filing Preparation',
        'description' => 'Prepare documents for tax filing',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => $targetDate->day,
        'lead_time_days' => $leadTimeDays,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Run generation
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated: 1 ticket(s)');

    // Verify ticket was created with correct due date
    $ticket = Ticket::where('name', 'Tax Filing Preparation')->first();
    expect($ticket)->not->toBeNull();

    // Due date should be the actual target date calculated by the service
    expect($ticket->due_date->toDateString())->toBe($actualTargetDate->toDateString());
})->group('recurring-tickets', 'integration');


/**
 * Test: Multiple templates generate multiple tickets
 * Requirements: 2.1
 */
test('multiple templates generate multiple tickets', function () {
    $today = Carbon::today();

    // Create multiple templates that should all generate today
    $templates = [
        [
            'name' => 'HR Weekly Review',
            'frequency' => TicketFrequency::Weekly,
            'day_value' => $today->dayOfWeekIso,
        ],
        [
            'name' => 'Finance Monthly Close',
            'frequency' => TicketFrequency::Monthly,
            'day_value' => $today->day,
        ],
    ];

    foreach ($templates as $data) {
        TicketTemplate::create([
            'name' => $data['name'],
            'description' => 'Auto-generated task',
            'project_id' => $this->project->id,
            'frequency' => $data['frequency'],
            'day_value' => $data['day_value'],
            'lead_time_days' => 0,
            'initial_status_id' => $this->status->id,
            'priority_id' => $this->priority->id,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    // Run generation
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated: 2 ticket(s)');

    // Verify both tickets were created
    expect(Ticket::where('name', 'HR Weekly Review')->exists())->toBeTrue();
    expect(Ticket::where('name', 'Finance Monthly Close')->exists())->toBeTrue();
})->group('recurring-tickets', 'integration');


/**
 * Test: Idempotent generation - running twice creates only one ticket
 * Requirements: 2.8
 */
test('idempotent generation prevents duplicates', function () {
    $today = Carbon::today();

    $template = TicketTemplate::create([
        'name' => 'Idempotent Test Task',
        'description' => 'This should only be created once',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => $today->day,
        'lead_time_days' => 0,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Run generation twice
    $this->artisan('tickets:generate-recurring')->assertSuccessful();
    $this->artisan('tickets:generate-recurring')->assertSuccessful();

    // Verify only one ticket exists
    expect(Ticket::where('name', 'Idempotent Test Task')->count())->toBe(1);

    // Verify only one generation record exists
    expect(TicketTemplateGeneration::where('ticket_template_id', $template->id)->count())->toBe(1);
})->group('recurring-tickets', 'integration');


/**
 * Test: Service can be used directly for programmatic generation
 */
test('service can generate tickets programmatically', function () {
    $today = Carbon::today();
    $service = app(RecurringTicketService::class);

    $template = TicketTemplate::create([
        'name' => 'Programmatic Generation Test',
        'description' => 'Generated via service',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => $today->day,
        'lead_time_days' => 0,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Generate ticket directly via service
    $ticket = $service->generateTicket($template, $today);

    expect($ticket)->toBeInstanceOf(Ticket::class);
    expect($ticket->name)->toBe('Programmatic Generation Test');
    expect($ticket->project_id)->toBe($this->project->id);
})->group('recurring-tickets', 'integration');
