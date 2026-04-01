<?php

namespace App\Http\Controllers\Api\Pm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * List tickets for a project with filters and pagination.
     *
     * GET /api/pm/projects/{project}/tickets
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $query = $project->tickets()
            ->active()
            ->with([
                'status:id,name,color,is_completed',
                'priority:id,name,color',
                'assignees:users.id,users.name,users.email,users.avatar_url',
                'epic:id,name',
                'creator:id,name',
            ]);

        // Filters
        if ($statusId = $request->input('status_id')) {
            $query->where('ticket_status_id', $statusId);
        }

        if ($assigneeId = $request->input('assignee_id')) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $assigneeId));
        }

        if ($priorityId = $request->input('priority_id')) {
            $query->where('priority_id', $priorityId);
        }

        if ($epicId = $request->input('epic_id')) {
            $query->where('epic_id', $epicId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $tickets = $query
            ->orderBy('updated_at', 'desc')
            ->simplePaginate($request->input('per_page', 20));

        $tickets->getCollection()->transform(fn (Ticket $ticket) => $this->formatTicket($ticket));

        return response()->json($tickets);
    }

    /**
     * Show a single ticket with full details.
     *
     * GET /api/pm/tickets/{ticket}
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;
        abort_if(! $project, 404, 'Ticket has no associated project.');
        $this->authorizeAccess($request->user(), $project);

        $ticket->load([
            'status:id,name,color,is_completed',
            'priority:id,name,color',
            'assignees:users.id,users.name,users.email,users.avatar_url',
            'epic:id,name',
            'creator:id,name',
            'project:id,name,ticket_prefix',
            'histories' => fn ($q) => $q->with('user:id,name', 'status:id,name,color')->latest()->limit(20),
        ]);

        return response()->json([
            'data' => $this->formatTicketDetail($ticket),
        ]);
    }

    /**
     * Create a new ticket in a project.
     *
     * POST /api/pm/projects/{project}/tickets
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticket_status_id' => ['nullable', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'exists:ticket_priorities,id'],
            'epic_id' => ['nullable', 'exists:epics,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Default to first status if not provided
        if (empty($data['ticket_status_id'])) {
            $firstStatus = $project->ticketStatuses()->orderBy('sort_order')->first();
            $data['ticket_status_id'] = $firstStatus?->id;
        }

        // Validate status belongs to project
        if (! empty($data['ticket_status_id'])) {
            $statusExists = $project->ticketStatuses()->where('id', $data['ticket_status_id'])->exists();
            abort_if(! $statusExists, 422, 'The selected status does not belong to this project.');
        }

        // Validate epic belongs to project
        if (! empty($data['epic_id'])) {
            $epicExists = $project->epics()->where('id', $data['epic_id'])->exists();
            abort_if(! $epicExists, 422, 'The selected epic does not belong to this project.');
        }

        $data['project_id'] = $project->id;
        $data['created_by'] = $request->user()->id;

        $assigneeIds = $data['assignee_ids'] ?? [];
        unset($data['assignee_ids']);

        // Ticket::booted() handles uuid generation and activity logging
        $ticket = Ticket::create($data);

        // Assign users
        if (! empty($assigneeIds)) {
            $ticket->syncAssigneesWithActivity($assigneeIds, $request->user());
        }

        $ticket->load([
            'status:id,name,color,is_completed',
            'priority:id,name,color',
            'assignees:users.id,users.name,users.email,users.avatar_url',
            'epic:id,name',
            'creator:id,name',
            'project:id,name,ticket_prefix',
        ]);

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => $this->formatTicketDetail($ticket),
        ], 201);
    }

    /**
     * Update a ticket.
     *
     * PUT /api/pm/tickets/{ticket}
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;
        abort_if(! $project, 404, 'Ticket has no associated project.');
        $this->authorizeAccess($request->user(), $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticket_status_id' => ['sometimes', 'exists:ticket_statuses,id'],
            'priority_id' => ['nullable', 'exists:ticket_priorities,id'],
            'epic_id' => ['nullable', 'exists:epics,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Validate status belongs to project
        if (! empty($data['ticket_status_id'])) {
            $statusExists = $project->ticketStatuses()->where('id', $data['ticket_status_id'])->exists();
            abort_if(! $statusExists, 422, 'The selected status does not belong to this project.');
        }

        // Validate epic belongs to project
        if (! empty($data['epic_id'])) {
            $epicExists = $project->epics()->where('id', $data['epic_id'])->exists();
            abort_if(! $epicExists, 422, 'The selected epic does not belong to this project.');
        }

        $assigneeIds = $data['assignee_ids'] ?? null;
        unset($data['assignee_ids']);

        // Ticket::booted() handles TicketHistory + ActivityLog on status change
        $ticket->update($data);

        // Sync assignees if provided
        if ($assigneeIds !== null) {
            $ticket->syncAssigneesWithActivity($assigneeIds, $request->user());
        }

        $ticket->load([
            'status:id,name,color,is_completed',
            'priority:id,name,color',
            'assignees:users.id,users.name,users.email,users.avatar_url',
            'epic:id,name',
            'creator:id,name',
            'project:id,name,ticket_prefix',
        ]);

        return response()->json([
            'message' => 'Ticket updated successfully.',
            'data' => $this->formatTicketDetail($ticket),
        ]);
    }

    /**
     * Archive (soft-delete) a ticket.
     *
     * DELETE /api/pm/tickets/{ticket}
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;
        abort_if(! $project, 404, 'Ticket has no associated project.');
        $this->authorizeAccess($request->user(), $project);

        $ticket->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Ticket archived successfully.',
        ]);
    }

    /**
     * Get all tickets assigned to the authenticated user across all projects.
     *
     * GET /api/pm/tickets/assigned-to-me
     */
    public function assignedToMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Ticket::query()
            ->active()
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'status:id,name,color,is_completed',
                'priority:id,name,color',
                'assignees:users.id,users.name,users.email,users.avatar_url',
                'project:id,name,ticket_prefix',
                'creator:id,name',
            ]);

        // Optional status filter
        if ($request->input('completed') === 'true') {
            $query->whereHas('status', fn ($q) => $q->where('is_completed', true));
        } elseif ($request->input('completed') === 'false') {
            $query->whereHas('status', fn ($q) => $q->where('is_completed', false));
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $tickets = $query
            ->orderByRaw("CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() THEN 0 ELSE 1 END")
            ->orderBy('due_date', 'asc')
            ->orderBy('updated_at', 'desc')
            ->simplePaginate($request->input('per_page', 20));

        $tickets->getCollection()->transform(fn (Ticket $ticket) => $this->formatTicket($ticket));

        return response()->json($tickets);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function authorizeAccess($user, Project $project): void
    {
        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_if(
            ! $project->members()->where('users.id', $user->id)->exists(),
            403,
            'You are not a member of this project.'
        );
    }

    protected function formatTicket(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'name' => $ticket->name,
            'ticket_status_id' => $ticket->ticket_status_id,
            'status' => $ticket->status ? [
                'id' => $ticket->status->id,
                'name' => $ticket->status->name,
                'color' => $ticket->status->color,
                'is_completed' => $ticket->status->is_completed,
            ] : null,
            'priority' => $ticket->priority ? [
                'id' => $ticket->priority->id,
                'name' => $ticket->priority->name,
                'color' => $ticket->priority->color,
            ] : null,
            'assignees' => $ticket->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
            ])->values(),
            'project' => $ticket->project ? [
                'id' => $ticket->project->id,
                'name' => $ticket->project->name,
            ] : null,
            'start_date' => $ticket->start_date?->toDateString(),
            'due_date' => $ticket->due_date?->toDateString(),
            'is_overdue' => $ticket->isOverdue(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    protected function formatTicketDetail(Ticket $ticket): array
    {
        return array_merge($this->formatTicket($ticket), [
            'description' => $ticket->description,
            'epic' => $ticket->epic ? [
                'id' => $ticket->epic->id,
                'name' => $ticket->epic->name,
            ] : null,
            'creator' => $ticket->creator ? [
                'id' => $ticket->creator->id,
                'name' => $ticket->creator->name,
            ] : null,
            'histories' => ($ticket->relationLoaded('histories') ? $ticket->histories : collect())->map(fn ($h) => [
                'id' => $h->id,
                'user' => $h->user ? ['id' => $h->user->id, 'name' => $h->user->name] : null,
                'status' => $h->status ? ['id' => $h->status->id, 'name' => $h->status->name, 'color' => $h->status->color] : null,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
