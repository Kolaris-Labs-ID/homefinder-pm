<?php

namespace App\Filament\Widgets;

use App\Models\WeeklyTodo;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\Epic;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CalendarWidget extends Widget
{

    protected static string $view = 'filament.widgets.calendar';
    
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];
    
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        // Allow all authenticated users to view the calendar widget
        return auth()->check();
    }

    public function getCalendarEvents(): array
    {
        $user = Auth::user();
        $events = [];

        // 1. Weekly Todos - Only user's own todos
        $weeklyTodos = WeeklyTodo::query()
            ->whereNotNull('deadline')
            ->where('user_id', $user->id)
            ->get();

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
                    'user' => $todo->user?->name ?? 'Unknown',
                    'url' => route('filament.admin.resources.weekly-todos.view', $todo->id),
                ],
            ];
        }

        // 2. Projects - Only active projects user is a member of (exclude archived/done)
        $projects = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereNull('archived_at')
            ->whereNull('done_at')
            ->whereHas('members', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

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

        // 3. Epics - Only epics from active projects user is a member of (exclude archived/done)
        $epics = Epic::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereHas('project', function($q) use ($user) {
                $q->whereNull('archived_at')
                  ->whereNull('done_at')
                  ->whereHas('members', function($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            })
            ->with('project')
            ->get();

        foreach ($epics as $epic) {
            $events[] = [
                'id' => 'epic_' . $epic->id,
                'title' => '🎯 ' . $epic->name,
                'start' => $epic->start_date->format('Y-m-d'),
                'end' => $epic->end_date->copy()->addDay()->format('Y-m-d'), // Add day for exclusive end
                'allDay' => true,
                'backgroundColor' => '#8b5cf6', // purple color for epics
                'textColor' => '#ffffff',
                'borderColor' => '#7c3aed',
                'extendedProps' => [
                    'type' => 'epic',
                    'description' => $epic->description,
                    'project' => $epic->project->name ?? 'Unknown',
                    'url' => url('/admin/epics-overview/' . $epic->project_id),
                ],
            ];
        }

        // 4. Assigned Tickets - Only non-archived tickets from active projects assigned to user
        $tickets = Ticket::query()
            ->whereNotNull('due_date')
            ->whereNull('archived_at')
            ->whereHas('project', function($q) {
                $q->whereNull('archived_at')->whereNull('done_at');
            })
            ->whereHas('assignees', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with(['project', 'status', 'priority', 'assignees'])
            ->get();

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

