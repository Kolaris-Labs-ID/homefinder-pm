<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use Filament\Widgets\Widget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

class ProjectTimeline extends Widget
{
    use HasWidgetShield;
    protected static string $view = 'filament.widgets.project-timeline';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 'full',
        'lg' => 6,
        'xl' => 6,
    ];

    protected static ?int $sort = 2;
    
    public string $filter = 'pinned';
    public string $viewMode = 'projects'; // 'projects' or 'tickets'
    
    public function getProjects()
    {
        $query = Project::query()
            ->with(['tickets' => function($q) {
                $q->with(['status', 'assignees', 'priority'])
                  ->orderBy('created_at', 'desc');
            }]);
            
        if ($this->filter === 'pinned') {
            // For pinned filter, include all pinned projects regardless of dates
            $query->whereNotNull('pinned_date')
                  ->orderBy('pinned_date', 'desc');
        } else {
            // For all projects, only show those with dates
            $query->whereNotNull('start_date')
                  ->whereNotNull('end_date')
                  ->orderBy('start_date');
        }
            
        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (!$userIsSuperAdmin) {
            $query->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }
            
        return $query->get();
    }
    
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }
    
    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }
    
    public function getTotalProjects()
    {
        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        // Count all projects with dates
        $allQuery = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        if (!$userIsSuperAdmin) {
            $allQuery->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }

        // Count pinned projects regardless of dates
        $pinnedQuery = Project::query()
            ->whereNotNull('pinned_date');

        if (!$userIsSuperAdmin) {
            $pinnedQuery->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }
            
        return [
            'all' => $allQuery->count(),
            'pinned' => $pinnedQuery->count(),
        ];
    }
    
    public function getTimelineRange()
    {
        $projects = $this->getProjects();
        
        if ($projects->isEmpty()) {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->addMonths(6)->endOfMonth();
            return [
                'start' => $startDate,
                'end' => $endDate,
                'months' => [],
                'total_days' => $startDate->diffInDays($endDate)
            ];
        }
        
        $earliestStart = $projects->min('start_date');
        $latestEnd = $projects->max('end_date');
        
        $startDate = Carbon::parse($earliestStart)->startOfMonth();
        $endDate = Carbon::parse($latestEnd)->endOfMonth();
        
        // Extend range to show context
        $startDate->subMonth();
        $endDate->addMonth();
        
        $months = [];
        $current = $startDate->copy();
        
        while ($current->lte($endDate)) {
            $months[] = [
                'date' => $current->copy(),
                'label' => $current->format('M Y'),
                'short' => $current->format('M'),
                'days' => $current->daysInMonth
            ];
            $current->addMonth();
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate,
            'months' => $months,
            'total_days' => $startDate->diffInDays($endDate)
        ];
    }
    
    protected function getViewData(): array
    {
        $projects = $this->getProjects();
        $today = Carbon::today();
        $counts = $this->getTotalProjects();
        $timelineRange = $this->getTimelineRange();
        
        $timelineData = [];
        
        foreach ($projects as $project) {
            // Get ticket statistics
            $ticketStats = [
                'total' => $project->tickets->count(),
                'completed' => $project->tickets->filter(function($ticket) {
                    return $ticket->status && in_array(strtolower($ticket->status->name), ['done', 'completed', 'closed']);
                })->count(),
                'overdue' => $project->tickets->filter(function($ticket) {
                    return $ticket->due_date && Carbon::parse($ticket->due_date)->isPast();
                })->count(),
            ];
            
            // Get recent active tickets (not done/completed)
            $activeTickets = $project->tickets->filter(function($ticket) {
                return !$ticket->status || !in_array(strtolower($ticket->status->name), ['done', 'completed', 'closed']);
            })->take(5)->map(function($ticket) {
                return [
                    'id' => $ticket->id,
                    'name' => $ticket->name,
                    'status' => $ticket->status?->name ?? 'No Status',
                    'status_color' => $ticket->status?->color ?? '#6b7280',
                    'priority' => $ticket->priority?->name ?? null,
                    'priority_color' => $ticket->priority?->color ?? null,
                    'due_date' => $ticket->due_date ? Carbon::parse($ticket->due_date)->format('d/m/Y') : null,
                    'is_overdue' => $ticket->due_date && Carbon::parse($ticket->due_date)->isPast(),
                    'assignees' => $ticket->assignees->pluck('name')->take(2)->join(', '),
                ];
            })->values();
            
            // Handle projects without dates (for pinned projects)
            if (!$project->start_date || !$project->end_date) {
                $timelineData[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'start_date' => null,
                    'end_date' => null,
                    'start_date_obj' => null,
                    'end_date_obj' => null,
                    'total_days' => null,
                    'past_days' => null,
                    'remaining_days' => null,
                    'progress_percent' => null,
                    'status' => 'No Timeline',
                    'status_color' => 'gray',
                    'left_percent' => 0,
                    'width_percent' => 0,
                    'ticket_stats' => $ticketStats,
                    'active_tickets' => $activeTickets,
                    'has_dates' => false,
                ];
                continue;
            }
            
            $startDate = Carbon::parse($project->start_date);
            $endDate = Carbon::parse($project->end_date);
            $totalDays = $startDate->diffInDays($endDate) + 1;
            
            if ($endDate->lt($startDate)) {
                continue;
            }
            
            // Calculate position and width for Gantt bar
            $rangeStart = $timelineRange['start'];
            $totalRangeDays = $timelineRange['total_days'];
            
            $startOffset = $rangeStart->diffInDays($startDate);
            $endOffset = $rangeStart->diffInDays($endDate);
            
            $leftPercent = ($startOffset / $totalRangeDays) * 100;
            $widthPercent = (($endOffset - $startOffset + 1) / $totalRangeDays) * 100;
            
            $pastDays = 0;
            $remainingDays = 0;
            $progressPercent = 0;
            
            if ($today->lt($startDate)) {
                $pastDays = 0;
                $remainingDays = $totalDays;
                $progressPercent = 0;
            } elseif ($today->gt($endDate)) {
                $pastDays = $totalDays;
                $remainingDays = 0;
                $progressPercent = 100;
            } else {
                $pastDays = $startDate->diffInDays($today);
                $remainingDays = $today->diffInDays($endDate);
                $progressPercent = ($pastDays / $totalDays) * 100;
            }
            
            $status = 'In Progress';
            $statusColor = 'blue';
            
            if ($today->gt($endDate)) {
                $status = 'Completed';
                $statusColor = 'green';
            } elseif ($project->remaining_days <= 0) {
                $status = 'Overdue';
                $statusColor = 'red';
            } elseif ($project->remaining_days <= 7) {
                $status = 'Approaching Deadline';
                $statusColor = 'yellow';
            } elseif ($today->lt($startDate)) {
                $status = 'Not Started';
                $statusColor = 'gray';
            }
            
            $timelineData[] = [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'start_date' => $startDate->format('d/m/Y'),
                'end_date' => $endDate->format('d/m/Y'),
                'start_date_obj' => $startDate,
                'end_date_obj' => $endDate,
                'total_days' => $totalDays,
                'past_days' => $pastDays,
                'remaining_days' => $project->remaining_days,
                'progress_percent' => round($progressPercent, 1),
                'status' => $status,
                'status_color' => $statusColor,
                'left_percent' => max(0, $leftPercent),
                'width_percent' => min(100 - max(0, $leftPercent), $widthPercent),
                'ticket_stats' => $ticketStats,
                'active_tickets' => $activeTickets,
                'has_dates' => true,
            ];
        }
        
        // Sort: projects with dates first (by start date), then projects without dates
        usort($timelineData, function($a, $b) {
            $aHasDates = $a['has_dates'] ?? ($a['start_date_obj'] !== null);
            $bHasDates = $b['has_dates'] ?? ($b['start_date_obj'] !== null);
            
            if ($aHasDates && !$bHasDates) return -1;
            if (!$aHasDates && $bHasDates) return 1;
            if (!$aHasDates && !$bHasDates) return 0;
            
            return $a['start_date_obj']->timestamp <=> $b['start_date_obj']->timestamp;
        });
        
        return [
            'projects' => $timelineData,
            'filter' => $this->filter,
            'viewMode' => $this->viewMode,
            'counts' => $counts,
            'timeline_range' => $timelineRange,
            'today_percent' => ($timelineRange['start']->diffInDays(Carbon::today()) / $timelineRange['total_days']) * 100,
        ];
    }
}