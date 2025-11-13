<?php

namespace App\Filament\Widgets;

use App\Models\WeeklyTodo;
use App\Models\Ticket;
use App\Models\Project;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CalendarWidget extends Widget
{
    use HasWidgetShield;

    protected static string $view = 'filament.widgets.calendar';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    public function getCalendarEvents(): array
    {
        $user = Auth::user();
        $events = [];

        // Check if user is super admin or C-level
        $isSuperAdmin = $user->hasRole('super_admin');
        $isCLevel = $user->hasAnyRole(['CEO', 'COO', 'CTO', 'admin']);

        // 1. Weekly Todos
        $weeklyTodosQuery = WeeklyTodo::query()
            ->whereNotNull('deadline');

        if (!$isSuperAdmin && !$isCLevel) {
            // Regular users see only their own todos
            $weeklyTodosQuery->where('user_id', $user->id);
        } elseif ($isCLevel && !$isSuperAdmin) {
            // C-level sees their division's todos if they're division head
            if ($user->is_division_head && $user->division) {
                $weeklyTodosQuery->whereHas('user', function($q) use ($user) {
                    $q->where('division', $user->division);
                });
            } else {
                $weeklyTodosQuery->where('user_id', $user->id);
            }
        }
        // Super admin sees all (no filter)

        $weeklyTodos = $weeklyTodosQuery->get();

        foreach ($weeklyTodos as $todo) {
            $events[] = [
                'id' => 'todo_' . $todo->id,
                'title' => '📋 ' . $todo->title,
                'start' => $todo->deadline->format('Y-m-d'),
                'end' => $todo->deadline->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => $this->getTodoStatusColor($todo->status),
                'textColor' => '#ffffff',
                'borderColor' => $this->getTodoStatusColor($todo->status),
                'extendedProps' => [
                    'type' => 'weekly_todo',
                    'description' => $todo->description,
                    'status' => $todo->status,
                    'user' => $todo->user->name ?? 'Unknown',
                    'url' => route('filament.admin.resources.weekly-todos.view', $todo->id),
                ],
            ];
        }

        // 2. Projects (user is member of or all if super admin)
        $projectsQuery = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        if (!$isSuperAdmin) {
            $projectsQuery->whereHas('members', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $projects = $projectsQuery->get();

        foreach ($projects as $project) {
            $events[] = [
                'id' => 'project_' . $project->id,
                'title' => '📁 ' . $project->name,
                'start' => $project->start_date->format('Y-m-d'),
                'end' => $project->end_date->copy()->addDay()->format('Y-m-d'), // Add day for exclusive end
                'allDay' => true,
                'backgroundColor' => '#3b82f6',
                'textColor' => '#ffffff',
                'borderColor' => '#2563eb',
                'extendedProps' => [
                    'type' => 'project',
                    'description' => $project->description,
                    'url' => route('filament.admin.resources.projects.view', $project->id),
                ],
            ];
        }

        // 3. Assigned Tickets
        $ticketsQuery = Ticket::query()
            ->whereNotNull('due_date')
            ->with(['project', 'status', 'priority', 'assignees']);

        if (!$isSuperAdmin) {
            $ticketsQuery->whereHas('assignees', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $tickets = $ticketsQuery->get();

        foreach ($tickets as $ticket) {
            $isOverdue = $ticket->isOverdue();
            $isCompleted = $ticket->status && $ticket->status->is_completed;
            
            $events[] = [
                'id' => 'ticket_' . $ticket->id,
                'title' => '🎫 ' . $ticket->uuid . ': ' . Str::limit($ticket->name, 30),
                'start' => $ticket->start_date 
                    ? $ticket->start_date->format('Y-m-d') 
                    : $ticket->due_date->copy()->subDays(1)->format('Y-m-d'),
                'end' => $ticket->due_date->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => $this->getTicketColor($ticket, $isOverdue, $isCompleted),
                'textColor' => '#ffffff',
                'borderColor' => $this->getTicketColor($ticket, $isOverdue, $isCompleted),
                'extendedProps' => [
                    'type' => 'ticket',
                    'uuid' => $ticket->uuid,
                    'description' => $ticket->description,
                    'status' => $ticket->status->name ?? 'Unknown',
                    'priority' => $ticket->priority->name ?? 'Normal',
                    'project' => $ticket->project->name ?? 'Unknown',
                    'isOverdue' => $isOverdue,
                    'isCompleted' => $isCompleted,
                    'url' => route('filament.admin.resources.tickets.view', $ticket->id),
                ],
            ];
        }

        return $events;
    }

    private function getTodoStatusColor(string $status): string
    {
        return match($status) {
            'done' => '#10b981',      // green
            'in_progress' => '#f59e0b', // amber
            'pending' => '#6b7280',    // gray
            default => '#6b7280',
        };
    }

    private function getTicketColor(Ticket $ticket, bool $isOverdue, bool $isCompleted): string
    {
        if ($isCompleted) {
            return '#10b981'; // green
        }
        
        if ($isOverdue) {
            return '#ef4444'; // red
        }

        // Use priority color if available
        if ($ticket->priority && $ticket->priority->color) {
            return $ticket->priority->color;
        }

        return '#3b82f6'; // default blue
    }
}

