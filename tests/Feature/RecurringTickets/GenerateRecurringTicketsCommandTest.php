<?php

/**
 * Feature Tests for GenerateRecurringTicketsCommand
 *
 * Tests the Artisan command that generates tickets from recurring templates.
 * Requirements: 2.1, 2.7
 */

use App\Enums\TicketFrequency;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateGeneration;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    // Clean up before each test
    TicketTemplateGeneration::query()->delete();
    TicketTemplate::query()->delete();
});

/**
 * Test successful ticket generation from active templates.
 * Requirements: 2.1, 2.7
 */
test('command generates tickets from active templates due today', function () {
    // Create project with status
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
    ]);
    $priority = TicketPriority::factory()->create();
    $creator = User::factory()->create();

    // Create template that should generate today (lead_time = 0, day = today)
    $today = Carbon::today();
    $template = TicketTemplate::factory()->create([
        'name' => 'Weekly Report',
        'project_id' => $project->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => $today->dayOfWeekIso,
        'lead_time_days' => 0,
        'initial_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $creator->id,
        'is_active' => true,
    ]);

    // Run the command
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated ticket')
        ->expectsOutputToContain('Generated: 1 ticket(s)');

    // Verify ticket was created
    expect(Ticket::where('name', 'Weekly Report')->exists())->toBeTrue();
    expect(TicketTemplateGeneration::where('ticket_template_id', $template->id)->exists())->toBeTrue();
})->group('recurring-tickets', 'command');


/**
 * Test dry-run mode shows what would be generated without creating tickets.
 * Requirements: 2.7
 */
test('command dry-run mode shows what would be generated without creating tickets', function () {
    // Create project with status
    $project = Project::factory()->create(['name' => 'Test Project']);
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'name' => 'To Do',
        'sort_order' => 1,
    ]);
    $priority = TicketPriority::factory()->create();
    $creator = User::factory()->create();

    // Create template that should generate today
    $today = Carbon::today();
    $template = TicketTemplate::factory()->create([
        'name' => 'Monthly Payroll',
        'project_id' => $project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => $today->day,
        'lead_time_days' => 0,
        'initial_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $creator->id,
        'is_active' => true,
    ]);

    // Run the command in dry-run mode
    $this->artisan('tickets:generate-recurring --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('Would generate ticket')
        ->expectsOutputToContain('Would generate: 1 ticket(s)');

    // Verify NO ticket was created
    expect(Ticket::where('name', 'Monthly Payroll')->exists())->toBeFalse();
    expect(TicketTemplateGeneration::where('ticket_template_id', $template->id)->exists())->toBeFalse();
})->group('recurring-tickets', 'command');


/**
 * Test command handles no active templates gracefully.
 * Requirements: 2.1
 */
test('command handles no active templates gracefully', function () {
    // Ensure no active templates exist
    TicketTemplate::query()->delete();

    // Run the command
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed: 0 template(s)')
        ->expectsOutputToContain('Generated: 0 ticket(s)');
})->group('recurring-tickets', 'command');


/**
 * Test command skips inactive templates.
 * Requirements: 2.1
 */
test('command skips inactive templates', function () {
    // Create project with status
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
    ]);
    $priority = TicketPriority::factory()->create();
    $creator = User::factory()->create();

    // Create INACTIVE template
    $today = Carbon::today();
    $template = TicketTemplate::factory()->create([
        'name' => 'Inactive Template',
        'project_id' => $project->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => $today->dayOfWeekIso,
        'lead_time_days' => 0,
        'initial_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $creator->id,
        'is_active' => false, // Inactive
    ]);

    // Run the command
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Processed: 0 template(s)')
        ->expectsOutputToContain('Generated: 0 ticket(s)');

    // Verify NO ticket was created
    expect(Ticket::where('name', 'Inactive Template')->exists())->toBeFalse();
})->group('recurring-tickets', 'command');


/**
 * Test command skips templates not due today.
 * Requirements: 2.1, 2.2
 */
test('command skips templates not due today', function () {
    // Create project with status
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
    ]);
    $priority = TicketPriority::factory()->create();
    $creator = User::factory()->create();

    // Create template for a different day
    $today = Carbon::today();
    $differentDay = $today->dayOfWeekIso === 7 ? 1 : $today->dayOfWeekIso + 1;

    $template = TicketTemplate::factory()->create([
        'name' => 'Future Template',
        'project_id' => $project->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => $differentDay, // Different day than today
        'lead_time_days' => 0,
        'initial_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $creator->id,
        'is_active' => true,
    ]);

    // Run the command
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Skipped')
        ->expectsOutputToContain('Generated: 0 ticket(s)');

    // Verify NO ticket was created
    expect(Ticket::where('name', 'Future Template')->exists())->toBeFalse();
})->group('recurring-tickets', 'command');


/**
 * Test command prevents duplicate generation for same period.
 * Requirements: 2.8
 */
test('command prevents duplicate generation for same period', function () {
    // Create project with status
    $project = Project::factory()->create();
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
    ]);
    $priority = TicketPriority::factory()->create();
    $creator = User::factory()->create();

    // Create template that should generate today
    $today = Carbon::today();
    $template = TicketTemplate::factory()->create([
        'name' => 'Duplicate Test',
        'project_id' => $project->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => $today->dayOfWeekIso,
        'lead_time_days' => 0,
        'initial_status_id' => $status->id,
        'priority_id' => $priority->id,
        'created_by' => $creator->id,
        'is_active' => true,
    ]);

    // Run the command first time
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful()
        ->expectsOutputToContain('Generated: 1');

    // Verify one ticket was created
    expect(Ticket::where('name', 'Duplicate Test')->count())->toBe(1);

    // Verify generation record exists
    expect(TicketTemplateGeneration::where('ticket_template_id', $template->id)->count())->toBe(1);

    // Run the command second time - should skip because already generated
    $this->artisan('tickets:generate-recurring')
        ->assertSuccessful();

    // Verify still only one ticket exists (idempotence)
    expect(Ticket::where('name', 'Duplicate Test')->count())->toBe(1);

    // Verify still only one generation record
    expect(TicketTemplateGeneration::where('ticket_template_id', $template->id)->count())->toBe(1);
})->group('recurring-tickets', 'command');


/**
 * Test dry-run mode with no active templates.
 */
test('dry-run mode handles no active templates gracefully', function () {
    // Ensure no active templates exist
    TicketTemplate::query()->delete();

    // Run the command in dry-run mode
    $this->artisan('tickets:generate-recurring --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('DRY RUN MODE')
        ->expectsOutputToContain('No active templates found');
})->group('recurring-tickets', 'command');
