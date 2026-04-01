<?php

namespace App\Http\Controllers\Api\Pm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TicketStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * List projects.
     *
     * By default returns only projects where the authenticated user is a member.
     * Pass ?all=true to list all projects (requires super_admin role).
     *
     * GET /api/pm/projects
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Project::query()
            ->active()
            ->with(['owner:id,name,email,avatar_url', 'ticketStatuses']);

        // Default: member-only. Allow ?all=true for super_admin.
        if ($request->boolean('all') && $user->hasRole('super_admin')) {
            // No membership filter
        } else {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $user->id));
        }

        // Optional search
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $projects = $query
            ->withCount('tickets')
            ->orderByRaw('pinned_date IS NULL, pinned_date DESC')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Project $project) => $this->formatProject($project));

        return response()->json([
            'data' => $projects,
        ]);
    }

    /**
     * Show a single project with stats.
     *
     * GET /api/pm/projects/{project}
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $project->loadCount('tickets');
        $project->load([
            'owner:id,name,email,avatar_url',
            'ticketStatuses' => fn ($q) => $q->orderBy('sort_order'),
            'epics:id,project_id,name',
        ]);

        $completedCount = $project->tickets()
            ->whereHas('status', fn ($q) => $q->where('is_completed', true))
            ->count();

        return response()->json([
            'data' => array_merge($this->formatProject($project), [
                'statuses' => $project->ticketStatuses->map(fn (TicketStatus $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'color' => $s->color,
                    'sort_order' => $s->sort_order,
                    'is_completed' => $s->is_completed,
                ]),
                'epics' => $project->epics->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                ]),
                'tickets_completed' => $completedCount,
            ]),
        ]);
    }

    /**
     * Create a new project.
     *
     * POST /api/pm/projects
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticket_prefix' => ['required', 'string', 'max:10'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $data['created_by'] = $request->user()->id;

        $project = Project::create($data);

        // Automatically add creator as a member
        $project->members()->attach($request->user()->id);

        // Create default statuses
        $defaults = [
            ['name' => 'To Do', 'sort_order' => 1, 'color' => '#6B7280', 'is_completed' => false],
            ['name' => 'In Progress', 'sort_order' => 2, 'color' => '#3B82F6', 'is_completed' => false],
            ['name' => 'Done', 'sort_order' => 3, 'color' => '#10B981', 'is_completed' => true],
        ];

        foreach ($defaults as $status) {
            $project->ticketStatuses()->create($status);
        }

        $project->loadCount('tickets');
        $project->load('owner:id,name,email,avatar_url');

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => $this->formatProject($project),
        ], 201);
    }

    /**
     * Update a project.
     *
     * PUT /api/pm/projects/{project}
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ticket_prefix' => ['sometimes', 'required', 'string', 'max:10'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $project->update($data);

        $project->loadCount('tickets');
        $project->load('owner:id,name,email,avatar_url');

        return response()->json([
            'message' => 'Project updated successfully.',
            'data' => $this->formatProject($project),
        ]);
    }

    /**
     * Archive (soft-delete) a project.
     *
     * DELETE /api/pm/projects/{project}
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $project->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Project archived successfully.',
        ]);
    }

    /**
     * List project members.
     *
     * GET /api/pm/projects/{project}/members
     */
    public function members(Request $request, Project $project): JsonResponse
    {
        $this->authorizeAccess($request->user(), $project);

        $members = $project->members()->select('users.id', 'users.name', 'users.email', 'users.avatar_url')->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ]);

        return response()->json([
            'data' => $members,
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

    protected function formatProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'ticket_prefix' => $project->ticket_prefix,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'is_pinned' => $project->is_pinned,
            'is_done' => $project->is_done,
            'progress_percentage' => $project->progress_percentage,
            'remaining_days' => $project->remaining_days,
            'tickets_count' => $project->tickets_count ?? 0,
            'owner' => $project->owner ? [
                'id' => $project->owner->id,
                'name' => $project->owner->name,
                'avatar_url' => $project->owner->avatar_url,
            ] : null,
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }
}
