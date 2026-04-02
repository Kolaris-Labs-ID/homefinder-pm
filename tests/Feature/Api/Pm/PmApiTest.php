<?php

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * @return array{user: User, project: Project, todo: TicketStatus, done: TicketStatus}
 */
function pmMemberProjectFixture(): array
{
    $user = User::factory()->create();
    $project = Project::factory()->create(['created_by' => $user->id]);
    $project->members()->attach($user->id);

    $todo = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 1,
        'name' => 'To Do',
        'is_completed' => false,
    ]);
    $done = TicketStatus::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 2,
        'name' => 'Done',
        'is_completed' => true,
    ]);

    return ['user' => $user, 'project' => $project, 'todo' => $todo, 'done' => $done];
}

it('rejects unauthenticated access to pm routes', function () {
    getJson('/api/pm/projects')->assertUnauthorized();
});

it('lists only projects where the user is a member', function () {
    $a = pmMemberProjectFixture();
    $b = pmMemberProjectFixture();

    Sanctum::actingAs($a['user']);

    $response = getJson('/api/pm/projects');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($a['project']->id)
        ->not->toContain($b['project']->id);
});

it('creates a project and adds creator as member with default statuses', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = postJson('/api/pm/projects', [
        'name' => 'API Test Project',
        'description' => 'From Pest',
        'ticket_prefix' => 'API',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'API Test Project')
        ->assertJsonPath('data.ticket_prefix', 'API');

    $project = Project::where('name', 'API Test Project')->first();
    expect($project)->not->toBeNull();
    expect($project->members()->where('users.id', $user->id)->exists())->toBeTrue();
    expect($project->ticketStatuses()->count())->toBe(3);
});

it('shows project details for a member', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/projects/{$fx['project']->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $fx['project']->id)
        ->assertJsonStructure(['data' => ['statuses']]);
});

it('forbids project show for non-members', function () {
    $fx = pmMemberProjectFixture();
    $stranger = User::factory()->create();
    Sanctum::actingAs($stranger);

    getJson("/api/pm/projects/{$fx['project']->id}")->assertForbidden();
});

it('updates a project for a member', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    putJson("/api/pm/projects/{$fx['project']->id}", [
        'name' => 'Renamed PM Project',
        'ticket_prefix' => $fx['project']->ticket_prefix,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed PM Project');

    expect($fx['project']->fresh()->name)->toBe('Renamed PM Project');
});

it('archives a project for a member', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    deleteJson("/api/pm/projects/{$fx['project']->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Project archived successfully.');

    expect($fx['project']->fresh()->archived_at)->not->toBeNull();
});

it('lists project members', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/projects/{$fx['project']->id}/members")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $fx['user']->id);
});

it('returns kanban board columns for a member', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/projects/{$fx['project']->id}/board")
        ->assertOk()
        ->assertJsonPath('data.project_id', $fx['project']->id)
        ->assertJsonStructure(['data' => ['columns']]);
});

it('moves a ticket to another status', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    putJson("/api/pm/tickets/{$ticket->id}/move", [
        'ticket_status_id' => $fx['done']->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.ticket_status_id', $fx['done']->id);

    expect($ticket->fresh()->ticket_status_id)->toBe($fx['done']->id);
});

it('rejects move when status belongs to another project', function () {
    $fx = pmMemberProjectFixture();
    $other = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    putJson("/api/pm/tickets/{$ticket->id}/move", [
        'ticket_status_id' => $other['todo']->id,
    ])->assertStatus(422);
});

it('lists tickets for a project with pagination shape', function () {
    $fx = pmMemberProjectFixture();
    Ticket::factory()->count(2)->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/projects/{$fx['project']->id}/tickets")
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'per_page']);
});

it('creates a ticket in a project', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    postJson("/api/pm/projects/{$fx['project']->id}/tickets", [
        'name' => 'New API Ticket',
        'description' => 'Body',
        'ticket_status_id' => $fx['todo']->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New API Ticket');

    expect(Ticket::where('name', 'New API Ticket')->exists())->toBeTrue();
});

it('shows ticket detail for a project member', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonPath('data.uuid', $ticket->uuid);
});

it('returns 404 for unknown ticket id', function () {
    $fx = pmMemberProjectFixture();
    Sanctum::actingAs($fx['user']);

    getJson('/api/pm/tickets/999999')->assertNotFound();
});

it('updates a ticket', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    putJson("/api/pm/tickets/{$ticket->id}", [
        'name' => 'Updated title',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated title');
});

it('archives a ticket', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    deleteJson("/api/pm/tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Ticket archived successfully.');

    expect($ticket->fresh()->archived_at)->not->toBeNull();
});

it('lists tickets assigned to the authenticated user', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    $ticket->assignees()->attach($fx['user']->id);

    Sanctum::actingAs($fx['user']);

    getJson('/api/pm/tickets/assigned-to-me')
        ->assertOk()
        ->assertJsonFragment(['id' => $ticket->id]);
});

it('lists comments for a ticket', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    TicketComment::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $fx['user']->id,
        'comment' => 'First note',
    ]);
    Sanctum::actingAs($fx['user']);

    getJson("/api/pm/tickets/{$ticket->id}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment', 'First note');
});

it('adds a comment to a ticket', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    Sanctum::actingAs($fx['user']);

    postJson("/api/pm/tickets/{$ticket->id}/comments", [
        'comment' => 'Hello from API',
    ])
        ->assertCreated()
        ->assertJsonPath('data.comment', 'Hello from API');
});

it('updates own comment', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    $comment = TicketComment::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $fx['user']->id,
        'comment' => 'Original',
    ]);
    Sanctum::actingAs($fx['user']);

    putJson("/api/pm/comments/{$comment->id}", [
        'comment' => 'Edited',
    ])
        ->assertOk()
        ->assertJsonPath('data.comment', 'Edited');
});

it('forbids updating another users comment', function () {
    $fx = pmMemberProjectFixture();
    $other = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    $comment = TicketComment::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $fx['user']->id,
        'comment' => 'Owners comment',
    ]);
    Sanctum::actingAs($other);

    putJson("/api/pm/comments/{$comment->id}", [
        'comment' => 'Hacked',
    ])->assertForbidden();
});

it('deletes own comment', function () {
    $fx = pmMemberProjectFixture();
    $ticket = Ticket::factory()->create([
        'project_id' => $fx['project']->id,
        'ticket_status_id' => $fx['todo']->id,
        'created_by' => $fx['user']->id,
    ]);
    $comment = TicketComment::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $fx['user']->id,
        'comment' => 'To remove',
    ]);
    Sanctum::actingAs($fx['user']);

    deleteJson("/api/pm/comments/{$comment->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Comment deleted successfully.');

    expect(TicketComment::find($comment->id))->toBeNull();
});

it('lists activity logs scoped to member projects', function () {
    $fx = pmMemberProjectFixture();
    ActivityLog::create([
        'subject_type' => Project::class,
        'subject_id' => $fx['project']->id,
        'project_id' => $fx['project']->id,
        'user_id' => $fx['user']->id,
        'event_type' => 'test_event',
        'meta' => ['ok' => true],
    ]);
    Sanctum::actingAs($fx['user']);

    getJson('/api/pm/activities?limit=5')
        ->assertOk()
        ->assertJsonPath('data.0.event_type', 'test_event');
});
