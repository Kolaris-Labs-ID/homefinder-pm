<?php

/**
 * Feature Tests for TicketTemplateResource
 *
 * Tests CRUD operations and permission checks for the Ticket Template resource.
 * Requirements: 1.1, 1.4, 8.2
 */

use App\Enums\TicketFrequency;
use App\Filament\Resources\TicketTemplateResource;
use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    // Create super_admin role with all permissions
    $this->role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    // Create ticket template permissions
    $permissions = [
        'view_any_ticket_template',
        'view_ticket_template',
        'create_ticket_template',
        'update_ticket_template',
        'delete_ticket_template',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->role->syncPermissions($permissions);

    // Create user with super_admin role
    $this->user = User::factory()->create();
    $this->user->assignRole($this->role);
    $this->actingAs($this->user);

    // Create a project with status for testing
    $this->project = Project::factory()->create();
    $this->status = TicketStatus::factory()->create([
        'project_id' => $this->project->id,
        'sort_order' => 10,
    ]);
    $this->priority = TicketPriority::factory()->create();
});

/**
 * Test: Ticket template can be created
 * Requirements: 1.1
 */
test('ticket template can be created', function () {
    $template = TicketTemplate::factory()->create([
        'name' => 'Monthly Payroll Task',
        'description' => 'Process monthly payroll',
        'project_id' => $this->project->id,
        'frequency' => TicketFrequency::Monthly,
        'day_value' => 25,
        'lead_time_days' => 5,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    expect($template)->toBeInstanceOf(TicketTemplate::class);
    expect($template->name)->toBe('Monthly Payroll Task');
    expect($template->frequency)->toBe(TicketFrequency::Monthly);
    expect($template->day_value)->toBe(25);
    expect($template->lead_time_days)->toBe(5);
    expect($template->is_active)->toBeTrue();
});

/**
 * Test: Ticket template can be read/viewed
 * Requirements: 1.4
 */
test('ticket template can be read', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
    ]);

    $foundTemplate = TicketTemplate::find($template->id);

    expect($foundTemplate)->not->toBeNull();
    expect($foundTemplate->id)->toBe($template->id);
    expect($foundTemplate->name)->toBe($template->name);
});

/**
 * Test: Ticket template can be updated
 * Requirements: 1.4
 */
test('ticket template can be updated', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => 1,
        'lead_time_days' => 3,
    ]);

    $template->update([
        'name' => 'Updated Template Name',
        'frequency' => TicketFrequency::Monthly,
        'day_value' => 15,
        'lead_time_days' => 7,
    ]);

    $template->refresh();

    expect($template->name)->toBe('Updated Template Name');
    expect($template->frequency)->toBe(TicketFrequency::Monthly);
    expect($template->day_value)->toBe(15);
    expect($template->lead_time_days)->toBe(7);
});

/**
 * Test: Ticket template can be deleted
 * Requirements: 1.4
 */
test('ticket template can be deleted', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
    ]);

    $templateId = $template->id;
    $template->delete();

    expect(TicketTemplate::find($templateId))->toBeNull();
});

/**
 * Test: Ticket template is_active can be toggled
 * Requirements: 1.5
 */
test('ticket template active status can be toggled', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'is_active' => true,
    ]);

    expect($template->is_active)->toBeTrue();

    $template->update(['is_active' => false]);
    $template->refresh();

    expect($template->is_active)->toBeFalse();

    $template->update(['is_active' => true]);
    $template->refresh();

    expect($template->is_active)->toBeTrue();
});

/**
 * Test: User without permission cannot view ticket templates
 * Requirements: 8.2
 */
test('user without permission cannot view ticket templates', function () {
    // Create a user without permissions
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    // Check that shouldRegisterNavigation returns false
    expect(TicketTemplateResource::shouldRegisterNavigation())->toBeFalse();
});

/**
 * Test: User with permission can view ticket templates
 * Requirements: 8.2
 */
test('user with permission can view ticket templates', function () {
    // User already has super_admin role with permissions
    expect(TicketTemplateResource::shouldRegisterNavigation())->toBeTrue();
});

/**
 * Test: Template relationships work correctly
 */
test('ticket template relationships work correctly', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'priority_id' => $this->priority->id,
        'created_by' => $this->user->id,
    ]);

    // Test project relationship
    expect($template->project)->not->toBeNull();
    expect($template->project->id)->toBe($this->project->id);

    // Test initial status relationship
    expect($template->initialStatus)->not->toBeNull();
    expect($template->initialStatus->id)->toBe($this->status->id);

    // Test priority relationship
    expect($template->priority)->not->toBeNull();
    expect($template->priority->id)->toBe($this->priority->id);

    // Test creator relationship
    expect($template->creator)->not->toBeNull();
    expect($template->creator->id)->toBe($this->user->id);
});

/**
 * Test: Template assignees relationship works correctly
 */
test('ticket template assignees relationship works correctly', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
    ]);

    // Create some users and attach as assignees
    $assignees = User::factory()->count(3)->create();
    $template->assignees()->attach($assignees->pluck('id'));

    $template->refresh();

    expect($template->assignees)->toHaveCount(3);
    expect($template->assignees->pluck('id')->toArray())
        ->toBe($assignees->pluck('id')->toArray());
});

/**
 * Test: Next generation date is calculated correctly
 * Requirements: 1.6
 */
test('next generation date is calculated correctly', function () {
    $template = TicketTemplate::factory()->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'frequency' => TicketFrequency::Weekly,
        'day_value' => 1, // Monday
        'lead_time_days' => 3,
    ]);

    $nextTargetDate = $template->getNextTargetDate();
    $nextGenerationDate = $template->getNextGenerationDate();

    // Generation date should be 3 days before target date
    expect((int) $nextGenerationDate->diffInDays($nextTargetDate))->toBe(3);

    // Target date should be a Monday
    expect($nextTargetDate->dayOfWeekIso)->toBe(1);
});

/**
 * Test: Active scope works correctly
 */
test('active scope filters correctly', function () {
    // Create active templates
    TicketTemplate::factory()->count(3)->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'is_active' => true,
    ]);

    // Create inactive templates
    TicketTemplate::factory()->count(2)->create([
        'project_id' => $this->project->id,
        'initial_status_id' => $this->status->id,
        'is_active' => false,
    ]);

    expect(TicketTemplate::active()->count())->toBe(3);
    expect(TicketTemplate::count())->toBe(5);
});
