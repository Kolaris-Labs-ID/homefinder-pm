<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectTicketsStatusWidget extends Widget
{
    use HasWidgetShield;

    protected static string $view = 'filament.widgets.project-tickets-status-widget';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md'      => 'full',
        'lg'      => 'full',
        'xl'      => 'full',
    ];

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = '30s';

    public string $viewMode = 'my_tickets'; // 'my_tickets' | 'all_tickets'

    public array $expandedProjects = [];

    public static function canView(): bool
    {
        return auth()->check();
    }

    /**
     * Get authenticated user typed.
     */
    private function getAuthUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user;
    }

    public function toggleViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function toggleProject(int $projectId): void
    {
        if (in_array($projectId, $this->expandedProjects)) {
            $this->expandedProjects = array_values(array_diff($this->expandedProjects, [$projectId]));
        } else {
            $this->expandedProjects[] = $projectId;
        }
    }

    /**
     * Get projects visible to the current user.
     */
    public function getProjects(): Collection
    {
        $user = $this->getAuthUser();
        if (! $user) {
            return collect();
        }

        $query = Project::query()
            ->whereNull('archived_at')
            ->whereNull('done_at')
            ->with(['ticketStatuses' => fn ($q) => $q->orderBy('sort_order')])
            ->withCount('tickets');

        if (! $user->canSeeAllPmData()) {
            $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get ticket counts per status for a project, optionally scoped to current user's assignments.
     *
     * Returns: [ status_id => count, ... ] + special key 'overdue' => count
     */
    public function getTicketCountsForProject(Project $project): array
    {
        $user = $this->getAuthUser();
        $myTickets = $this->viewMode === 'my_tickets';

        $baseQuery = Ticket::query()
            ->where('project_id', $project->id)
            ->whereNull('archived_at');

        if ($myTickets && $user) {
            $baseQuery->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
        }

        // Count per status
        $statusCounts = (clone $baseQuery)
            ->select('ticket_status_id', DB::raw('count(*) as count'))
            ->groupBy('ticket_status_id')
            ->pluck('count', 'ticket_status_id')
            ->toArray();

        // Overdue count
        $overdueCount = (clone $baseQuery)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->whereHas('status', fn ($q) => $q->where('is_completed', false))
            ->count();

        return [
            'by_status' => $statusCounts,
            'overdue'   => $overdueCount,
            'total'     => array_sum($statusCounts),
        ];
    }

    /**
     * Get tickets for a specific project and status (for expanded detail view).
     */
    public function getTicketsForProjectStatus(int $projectId, int $statusId): Collection
    {
        $user = $this->getAuthUser();
        $myTickets = $this->viewMode === 'my_tickets';

        $query = Ticket::query()
            ->where('project_id', $projectId)
            ->where('ticket_status_id', $statusId)
            ->whereNull('archived_at')
            ->with(['assignees:id,name', 'priority:id,name,color'])
            ->orderBy('due_date')
            ->limit(20);

        if ($myTickets && $user) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->get();
    }

    /**
     * Get overdue tickets for a project (for expanded detail view).
     */
    public function getOverdueTicketsForProject(int $projectId): Collection
    {
        $user = $this->getAuthUser();
        $myTickets = $this->viewMode === 'my_tickets';

        $query = Ticket::query()
            ->where('project_id', $projectId)
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->whereHas('status', fn ($q) => $q->where('is_completed', false))
            ->with(['assignees:id,name', 'status:id,name,color', 'priority:id,name,color'])
            ->orderBy('due_date')
            ->limit(20);

        if ($myTickets && $user) {
            $query->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query->get();
    }

    /**
     * Build a complete dataset for rendering: projects → statuses → counts + tickets (if expanded).
     */
    public function getWidgetData(): Collection
    {
        $projects = $this->getProjects();

        return $projects->map(function (Project $project) {
            $counts = $this->getTicketCountsForProject($project);
            $isExpanded = in_array($project->id, $this->expandedProjects);

            $statusData = $project->ticketStatuses->map(function (TicketStatus $status) use ($counts, $project, $isExpanded) {
                $count = $counts['by_status'][$status->id] ?? 0;
                return [
                    'id'       => $status->id,
                    'name'     => $status->name,
                    'color'    => $status->color ?? '#6b7280',
                    'count'    => $count,
                    'tickets'  => $isExpanded ? $this->getTicketsForProjectStatus($project->id, $status->id) : collect(),
                ];
            });

            return [
                'project'     => $project,
                'statuses'    => $statusData,
                'overdue'     => $counts['overdue'],
                'total'       => $counts['total'],
                'is_expanded' => $isExpanded,
                'overdue_tickets' => $isExpanded ? $this->getOverdueTicketsForProject($project->id) : collect(),
            ];
        });
    }
}
