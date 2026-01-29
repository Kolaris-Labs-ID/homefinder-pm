<?php

/**
 * Tests for Ticket Auto-Archive Feature
 *
 * These tests verify that tickets are automatically archived when
 * their parent project is marked as done or archived.
 */

use App\Models\Project;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Services\ProjectArchiveService;

beforeEach(function () {
    // Clean up before each test
    Ticket::query()->delete();
    Project::query()->delete();
});

/**
 * **Feature: ticket-auto-archive, Property 1: Tickets are archived when project is archived**
 *
 * For any project with tickets, when the project is archived,
 * all tickets in the project SHALL be automatically archived.
 */
test('tickets are automatically archived when project is archived', function () {
    $service = new ProjectArchiveService();

    // Create a user for authentication and archiving
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project with tickets
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create tickets
    $ticketCount = 3;
    $tickets = Ticket::factory()->count($ticketCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Verify tickets are not archived initially
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeFalse();
    }

    // Archive the project
    $service->archive($project, $user);

    // Refresh tickets from database
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->archived_at)->not->toBeNull(
            "Ticket should be archived when project is archived"
        );
        expect($ticket->archived_by)->toBe($user->id,
            "Ticket archived_by should reference the user who archived the project"
        );
        expect($ticket->is_archived)->toBeTrue();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 2: Tickets are archived when project is marked as done**
 *
 * For any project with tickets, when the project is marked as done,
 * all tickets in the project SHALL be automatically archived.
 */
test('tickets are automatically archived when project is marked as done', function () {
    $service = new ProjectArchiveService();

    // Create a user for authentication
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project with tickets
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create tickets
    $ticketCount = 3;
    $tickets = Ticket::factory()->count($ticketCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Verify tickets are not archived initially
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeFalse();
    }

    // Mark project as done
    $service->markAsDone($project, false);

    // Refresh tickets from database
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->archived_at)->not->toBeNull(
            "Ticket should be archived when project is marked as done"
        );
        expect($ticket->is_archived)->toBeTrue();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 3: Tickets are restored when project is unarchived**
 *
 * For any archived project with archived tickets, when the project is restored,
 * all archived tickets in the project SHALL be automatically restored.
 */
test('tickets are automatically restored when project is unarchived', function () {
    $service = new ProjectArchiveService();

    // Create a user for authentication
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create an archived project
    $project = Project::factory()->create([
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create archived tickets
    $ticketCount = 3;
    $tickets = Ticket::factory()->count($ticketCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
    ]);

    // Verify tickets are archived initially
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeTrue();
    }

    // Restore the project
    $service->restore($project);

    // Refresh tickets from database
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->archived_at)->toBeNull(
            "Ticket should be restored when project is restored"
        );
        expect($ticket->archived_by)->toBeNull();
        expect($ticket->is_archived)->toBeFalse();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 4: Tickets are restored when project is unmarked as done**
 *
 * For any done project with archived tickets, when the project's done status is removed,
 * all archived tickets in the project SHALL be automatically restored.
 */
test('tickets are automatically restored when project is unmarked as done', function () {
    $service = new ProjectArchiveService();

    // Create a user for authentication
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a done project
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
        'done_at' => now()->subDays(5),
        'is_auto_done' => false,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create archived tickets
    $ticketCount = 3;
    $tickets = Ticket::factory()->count($ticketCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
    ]);

    // Verify tickets are archived initially
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeTrue();
    }

    // Unmark the project as done
    $service->unmarkDone($project);

    // Refresh tickets from database
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->archived_at)->toBeNull(
            "Ticket should be restored when project is unmarked as done"
        );
        expect($ticket->archived_by)->toBeNull();
        expect($ticket->is_archived)->toBeFalse();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 5: Only non-archived tickets are archived**
 *
 * When a project is archived or marked as done, only tickets that are not already
 * archived SHALL be affected. Previously archived tickets should retain their original
 * archive metadata.
 */
test('only non-archived tickets are archived when project is archived', function () {
    $service = new ProjectArchiveService();

    // Create users
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $this->actingAs($user2);

    // Create a project
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create a pre-archived ticket
    $previousArchiveTime = now()->subDays(10);
    $preArchivedTicket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => $previousArchiveTime,
        'archived_by' => $user1->id,
    ]);

    // Create a non-archived ticket
    $nonArchivedTicket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Archive the project
    $service->archive($project, $user2);

    // Refresh tickets
    $preArchivedTicket->refresh();
    $nonArchivedTicket->refresh();

    // Pre-archived ticket should retain original archive metadata
    expect($preArchivedTicket->archived_by)->toBe($user1->id,
        "Pre-archived ticket should retain original archived_by"
    );

    // Non-archived ticket should now be archived
    expect($nonArchivedTicket->archived_at)->not->toBeNull();
    expect($nonArchivedTicket->archived_by)->toBe($user2->id);
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 6: Ticket active scope excludes archived tickets**
 *
 * The Ticket::active() scope SHALL only return tickets where archived_at is null.
 */
test('ticket active scope excludes archived tickets', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create();

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create active tickets
    $activeTickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Create archived tickets
    $archivedTickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now(),
        'archived_by' => $user->id,
    ]);

    // Query using active scope
    $result = Ticket::active()->where('project_id', $project->id)->get();

    // Should only return active tickets
    expect($result->count())->toBe(2);

    foreach ($result as $ticket) {
        expect($ticket->archived_at)->toBeNull();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 7: Ticket archived scope includes only archived tickets**
 *
 * The Ticket::archived() scope SHALL only return tickets where archived_at is not null.
 */
test('ticket archived scope includes only archived tickets', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create();

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create active tickets
    $activeTickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Create archived tickets
    $archivedTickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now(),
        'archived_by' => $user->id,
    ]);

    // Query using archived scope
    $result = Ticket::archived()->where('project_id', $project->id)->get();

    // Should only return archived tickets
    expect($result->count())->toBe(2);

    foreach ($result as $ticket) {
        expect($ticket->archived_at)->not->toBeNull();
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 8: archiveProjectTickets returns count of archived tickets**
 *
 * The archiveProjectTickets method SHALL return the number of tickets that were archived.
 */
test('archiveProjectTickets returns count of archived tickets', function () {
    $service = new ProjectArchiveService();

    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create();

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create active tickets
    $activeCount = 3;
    Ticket::factory()->count($activeCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Create already archived ticket
    Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
    ]);

    // Archive project tickets
    $count = $service->archiveProjectTickets($project, $user);

    // Should return the count of newly archived tickets (not the already archived one)
    expect($count)->toBe($activeCount);
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 9: restoreProjectTickets returns count of restored tickets**
 *
 * The restoreProjectTickets method SHALL return the number of tickets that were restored.
 */
test('restoreProjectTickets returns count of restored tickets', function () {
    $service = new ProjectArchiveService();

    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create();

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create archived tickets
    $archivedCount = 3;
    Ticket::factory()->count($archivedCount)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
    ]);

    // Create active ticket
    Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Restore project tickets
    $count = $service->restoreProjectTickets($project);

    // Should return the count of restored tickets (not the already active one)
    expect($count)->toBe($archivedCount);
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 10: Empty project archiving handles gracefully**
 *
 * When a project with no tickets is archived, the archiveProjectTickets method
 * SHALL complete without error and return 0.
 */
test('archiving project with no tickets completes gracefully', function () {
    $service = new ProjectArchiveService();

    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project with no tickets
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Archive project tickets (should not throw error)
    $count = $service->archiveProjectTickets($project, $user);

    expect($count)->toBe(0);
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 11: Ticket archivedBy relationship works correctly**
 *
 * The Ticket::archivedBy() relationship SHALL return the User who archived the ticket.
 */
test('ticket archivedBy relationship returns correct user', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create();

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create an archived ticket
    $ticket = Ticket::factory()->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now(),
        'archived_by' => $user->id,
    ]);

    // Test relationship
    expect($ticket->archivedBy)->not->toBeNull();
    expect($ticket->archivedBy->id)->toBe($user->id);
    expect($ticket->archivedBy->name)->toBe($user->name);
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 12: Project observer triggers ticket archiving**
 *
 * When a project is updated to be archived via the model (triggering the observer),
 * tickets SHALL be automatically archived.
 */
test('project observer triggers ticket archiving on project archive', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create tickets
    $tickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Verify tickets are not archived
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeFalse();
    }

    // Archive project directly (this triggers the observer)
    $project->update([
        'archived_at' => now(),
        'archived_by' => $user->id,
    ]);

    // Refresh and verify tickets are now archived
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->is_archived)->toBeTrue(
            "Ticket should be archived when project observer detects archive"
        );
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 13: Project observer triggers ticket archiving on done**
 *
 * When a project is updated to be done via the model (triggering the observer),
 * tickets SHALL be automatically archived.
 */
test('project observer triggers ticket archiving on project done', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a project
    $project = Project::factory()->create([
        'archived_at' => null,
        'archived_by' => null,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create tickets
    $tickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Verify tickets are not archived
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeFalse();
    }

    // Mark project as done directly (this triggers the observer)
    $project->update([
        'done_at' => now(),
    ]);

    // Refresh and verify tickets are now archived
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->is_archived)->toBeTrue(
            "Ticket should be archived when project observer detects done status"
        );
    }
})->group('ticket-auto-archive');

/**
 * **Feature: ticket-auto-archive, Property 14: Project observer triggers ticket restore**
 *
 * When an archived project is restored via the model (triggering the observer),
 * tickets SHALL be automatically restored.
 */
test('project observer triggers ticket restore on project restore', function () {
    // Create a user
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create an archived project
    $project = Project::factory()->create([
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
        'done_at' => null,
    ]);

    // Create a ticket status for the project
    $status = TicketStatus::factory()->create([
        'project_id' => $project->id,
    ]);

    // Create archived tickets
    $tickets = Ticket::factory()->count(2)->create([
        'project_id' => $project->id,
        'ticket_status_id' => $status->id,
        'archived_at' => now()->subDays(5),
        'archived_by' => $user->id,
    ]);

    // Verify tickets are archived
    foreach ($tickets as $ticket) {
        expect($ticket->is_archived)->toBeTrue();
    }

    // Restore project directly (this triggers the observer)
    $project->update([
        'archived_at' => null,
        'archived_by' => null,
    ]);

    // Refresh and verify tickets are now restored
    foreach ($tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->is_archived)->toBeFalse(
            "Ticket should be restored when project observer detects restore"
        );
    }
})->group('ticket-auto-archive');
