<?php

namespace App\Http\Controllers\Api\Pm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    /**
     * Get the Kanban board view for a project.
     *
     * Returns statuses (columns) ordered by sort_order, each containing its tickets.
     *
     * GET /api/pm/projects/{project}/board
     */
    public function board(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $statuses = $project->ticketStatuses()
            ->orderBy('sort_order')
            ->get();

        // Build filter query for tickets
        $ticketQuery = $project->tickets()->active()->with([
            'assignees:users.id,users.name,users.email,users.avatar_url',
            'priority:id,name,color',
            'epic:id,name',
            'creator:id,name',
        ]);

        // Optional filters
        if ($assigneeId = $request->input('assignee_id')) {
            $ticketQuery->whereHas('assignees', fn ($q) => $q->where('users.id', $assigneeId));
        }

        if ($priorityId = $request->input('priority_id')) {
            $ticketQuery->where('priority_id', $priorityId);
        }

        if ($epicId = $request->input('epic_id')) {
            $ticketQuery->where('epic_id', $epicId);
        }

        if ($search = $request->input('search')) {
            $ticketQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        $tickets = $ticketQuery->get();

        // Group tickets by status
        $ticketsByStatus = $tickets->groupBy('ticket_status_id');

        $columns = $statuses->map(fn (TicketStatus $status) => [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'sort_order' => $status->sort_order,
            'is_completed' => $status->is_completed,
            'tickets' => ($ticketsByStatus->get($status->id) ?? collect())->map(
                fn (Ticket $ticket) => $this->formatTicketCard($ticket)
            )->values(),
        ]);

        return response()->json([
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'columns' => $columns,
            ],
        ]);
    }

    /**
     * Move a ticket to a different status (Kanban drag-drop).
     *
     * PUT /api/pm/tickets/{ticket}/move
     */
    public function moveTicket(Request $request, Ticket $ticket): JsonResponse
    {
        $project = $ticket->project;

        abort_if(! $project, 404, 'Ticket has no associated project.');

        $this->authorizeAccess($request->user(), $project);

        $data = $request->validate([
            'ticket_status_id' => ['required', 'exists:ticket_statuses,id'],
        ]);

        // Ensure the target status belongs to the same project
        $targetStatus = TicketStatus::where('id', $data['ticket_status_id'])
            ->where('project_id', $project->id)
            ->first();

        abort_if(! $targetStatus, 422, 'The target status does not belong to this project.');

        // Skip if already in the same status
        if ($ticket->ticket_status_id === $targetStatus->id) {
            return response()->json([
                'message' => 'Ticket is already in this status.',
                'data' => $this->formatTicketCard($ticket->load([
                    'assignees:users.id,users.name,users.email,users.avatar_url',
                    'priority:id,name,color',
                    'epic:id,name',
                    'creator:id,name',
                ])),
            ]);
        }

        // Update triggers TicketHistory + ActivityLog via Ticket::booted()
        $ticket->update([
            'ticket_status_id' => $targetStatus->id,
        ]);

        $ticket->load([
            'assignees:users.id,users.name,users.email,users.avatar_url',
            'priority:id,name,color',
            'epic:id,name',
            'creator:id,name',
            'status',
        ]);

        return response()->json([
            'message' => 'Ticket moved successfully.',
            'data' => $this->formatTicketCard($ticket),
        ]);
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

    protected function formatTicketCard(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'name' => $ticket->name,
            'description' => $ticket->description,
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
            'epic' => $ticket->epic ? [
                'id' => $ticket->epic->id,
                'name' => $ticket->epic->name,
            ] : null,
            'assignees' => $ticket->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
            ])->values(),
            'creator' => $ticket->creator ? [
                'id' => $ticket->creator->id,
                'name' => $ticket->creator->name,
            ] : null,
            'start_date' => $ticket->start_date?->toDateString(),
            'due_date' => $ticket->due_date?->toDateString(),
            'is_overdue' => $ticket->isOverdue(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
