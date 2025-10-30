<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\Project;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use App\Filament\Actions\ExportTicketsAction;
use App\Exports\TicketsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Form;

class ProjectBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static string $view = 'filament.pages.project-board';
    protected static ?string $title = 'Project Board';
    protected static ?string $navigationLabel = 'Project Board';
    protected static ?string $navigationGroup = 'Project Management';
    protected static ?int $navigationSort = 4;

    public function getSubheading(): ?string
    {
        return 'Kanban board for ticket management';
    }
    protected static ?string $slug = 'project-board/{project_id?}';

    public ?Project $selectedProject = null;

    public Collection $projects;

    public Collection $ticketStatuses;

    public ?Ticket $selectedTicket = null;

    public ?int $selectedProjectId = null;
    
    public array $sortOrders = [];
    
    public array $selectedUserIds = [];
    
    public Collection $projectUsers;

    public function mount($project_id = null): void
    {
        if (auth()->user()->hasRole(['super_admin'])) {
            $this->projects = Project::all();
        } else {
            $this->projects = auth()->user()->projects;
        }

        if ($project_id) {
            $this->selectedProjectId = (int) $project_id;
            $this->selectedProject = Project::find($project_id);
            $this->loadProjectUsers();
            $this->loadTicketStatuses();
        } else {
            $this->ticketStatuses = collect();
            $this->projectUsers = collect();
        }
    }

    public function updatedSelectedProjectId($value): void
    {
        if ($value) {
            $this->selectProject($value);
        } else {
            $this->selectedProject = null;
            $this->ticketStatuses = collect();
            $this->projectUsers = collect();
            $this->selectedUserIds = [];
            $this->redirect(static::getUrl());
        }
    }

    public function selectProject(int $projectId): void
    {
        $this->selectedTicket = null;
        $this->ticketStatuses = collect();
        $this->selectedProjectId = $projectId;
        $this->selectedProject = Project::find($projectId);
        $this->selectedUserIds = [];

        if ($this->selectedProject) {
            $url = static::getUrl(['project_id' => $projectId]);
            $this->redirect($url);

            $this->loadProjectUsers();
            $this->loadTicketStatuses();
        }
    }

    public function loadTicketStatuses(): void
    {
        if (! $this->selectedProject) {
            $this->ticketStatuses = collect();
    
            return;
        }
    
        $this->ticketStatuses = $this->selectedProject->ticketStatuses()
            ->with([
                'tickets' => function ($query) {
                    $query->with([
                        'assignees:id,name',
                        'status:id,name,color,is_completed',
                        'priority:id,name,color',
                        'creator:id,name'
                    ])
                    ->select('id', 'project_id', 'ticket_status_id', 'priority_id', 'name', 'description', 'uuid', 'due_date', 'created_at', 'updated_at', 'created_by')
                    ->when(!empty($this->selectedUserIds), function ($query) {
                        $query->whereHas('assignees', function ($assigneeQuery) {
                            $assigneeQuery->whereIn('users.id', $this->selectedUserIds);
                        });
                    })
                    ->orderBy('id', 'asc');
                }
            ])
            ->select('id', 'project_id', 'name', 'color', 'sort_order', 'is_completed')
            ->orderBy('sort_order')
            ->get();
            
        $this->ticketStatuses->each(function ($status) {
            $sortOrder = $this->sortOrders[$status->id] ?? 'date_created_newest';
            $status->tickets = $this->applySorting($status->tickets, $sortOrder);
        });
    
    }
    
    public function loadProjectUsers(): void
    {
        if (! $this->selectedProject) {
            $this->projectUsers = collect();
            return;
        }
        
        // Get only users who are assigned to tickets in this project
        $ticketAssigneeIds = $this->selectedProject->tickets()
            ->with('assignees')
            ->get()
            ->flatMap(function ($ticket) {
                return $ticket->assignees->pluck('id');
            })
            ->unique()
            ->filter();
            
        $this->projectUsers = User::whereIn('id', $ticketAssigneeIds)
            ->orderBy('name')
            ->get();
    }
    
    public function updatedSelectedUserIds(): void
    {
        $this->loadTicketStatuses();
    }
    
    public function clearUserFilter(): void
    {
        $this->selectedUserIds = [];
        $this->loadTicketStatuses();
    }
    
    public function setSortOrder($statusId, $sortOrder)
    {
        $this->sortOrders[$statusId] = $sortOrder;
        $this->loadTicketStatuses();
    }
    
    private function applySorting($tickets, $sortOrder)
    {
        switch ($sortOrder) {
            case 'date_created_newest':
                return $tickets->sortByDesc('created_at');
            case 'date_created_oldest':
                return $tickets->sortBy('created_at');
            case 'card_name_alphabetical':
                return $tickets->sortBy('name');
            case 'due_date':
                return $tickets->sortBy(function ($ticket) {
                    return $ticket->due_date ?? '9999-12-31';
                });
            case 'priority':
                return $tickets->sortBy(function ($ticket) {
                    return $ticket->priority ? $ticket->priority->id : 999;
                });
            default:
                return $tickets->sortByDesc('created_at');
        }
    }

    #[On('ticket-moved')]
    public function moveTicket($ticketId, $newStatusId): void
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket && $ticket->project_id === $this->selectedProject?->id) {
            if (!$this->canManageTicket($ticket)) {
                Notification::make()
                    ->title('Permission Denied')
                    ->body('You do not have permission to move this ticket.')
                    ->danger()
                    ->send();
                return;
            }

            $ticket->update([
                'ticket_status_id' => $newStatusId,
            ]);

            $this->loadTicketStatuses();

            $this->dispatch('ticket-updated');

            Notification::make()
                ->title('Ticket Updated')
                ->success()
                ->send();
        }
    }

    #[On('refresh-board')]
    public function refreshBoard(): void
    {
        $this->loadTicketStatuses();
        $this->dispatch('ticket-updated');
    }

    public function showTicketDetails(int $ticketId): void
    {
        $ticket = Ticket::with(['assignees', 'status', 'project', 'priority'])->find($ticketId);

        if (! $ticket) {
            Notification::make()
                ->title('Ticket Not Found')
                ->danger()
                ->send();

            return;
        }

        
        $url = TicketResource::getUrl('view', ['record' => $ticketId]);
        $this->js("window.open('{$url}', '_blank')");
    }

    public function closeTicketDetails(): void
    {
        $this->selectedTicket = null;
    }

    public function editTicket(int $ticketId): void
    {
        $ticket = Ticket::find($ticketId);

        if (! $this->canEditTicket($ticket)) {
            Notification::make()
                ->title('Permission Denied')
                ->body('You do not have permission to edit this ticket.')
                ->danger()
                ->send();

            return;
        }

        $this->redirect(TicketResource::getUrl('edit', ['record' => $ticketId]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_ticket')
                ->label('New Ticket')
                ->icon('heroicon-m-plus')
                ->visible(fn () => $this->selectedProject !== null && auth()->user()->can('create_ticket'))
                ->url(fn (): string => TicketResource::getUrl('create', [
                    'project_id' => $this->selectedProject?->id,
                    'ticket_status_id' => $this->selectedProject?->ticketStatuses->first()?->id,
                ]))
                ->openUrlInNewTab(),
    
            Action::make('refresh_board')
                ->label('Refresh Board')
                ->icon('heroicon-m-arrow-path')
                ->action('refreshBoard')
                ->color('warning'),
            ExportTicketsAction::make()
                ->visible(fn () => $this->selectedProject !== null && auth()->user()->hasRole(['super_admin'])),
            
            Action::make('filter_users')
                ->label('Filter by User')
                ->icon('heroicon-m-user-group')
                ->visible(fn () => $this->selectedProject !== null && $this->projectUsers->isNotEmpty())
                ->form([
                    CheckboxList::make('selectedUserIds')
                        ->label('Select Users to Filter')
                        ->options(fn () => $this->projectUsers->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                ])
                ->action(function (array $data) {
                    $this->selectedUserIds = $data['selectedUserIds'] ?? [];
                    $this->loadTicketStatuses();
                    
                    $userCount = count($this->selectedUserIds);
                    if ($userCount > 0) {
                        Notification::make()
                            ->title('Filter Applied')
                            ->body("Showing tickets for {$userCount} selected user(s)")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Filter Cleared')
                            ->body('Showing all tickets')
                            ->info()
                            ->send();
                    }
                })
                ->fillForm([
                    'selectedUserIds' => $this->selectedUserIds,
                ])
                ->modalWidth('md')
                ->color('info'),
        ];
    }

    private function canViewTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        if (! auth()->user()->can('view_ticket')) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canEditTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        // Check Filament Shield permission for updating tickets
        if (! auth()->user()->can('update_ticket')) {
            return false;
        }

        // Additional business logic: user can edit if they are:
        // 1. Super admin (already covered by permission above)
        // 2. The ticket creator
        // 3. Assigned to the ticket
        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canManageTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        // Check Filament Shield permission for updating tickets (moving is updating)
        if (! auth()->user()->can('update_ticket')) {
            return false;
        }

        // Additional business logic: user can manage if they are:
        // 1. Super admin (already covered by permission above)
        // 2. The ticket creator
        // 3. Assigned to the ticket
        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }


    public function exportTickets(array $selectedColumns): void
    {
        if (empty($selectedColumns)) {
            Notification::make()
                ->title('Export Failed')
                ->body('Please select at least one column to export.')
                ->danger()
                ->send();
            return;
        }

        $tickets = collect();
        
        if ($this->selectedProject) {
            $tickets = $this->selectedProject->tickets()
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($this->ticketStatuses->isNotEmpty()) {
            $ticketIds = $this->ticketStatuses->flatMap(function ($status) {
                return $status->tickets->pluck('id');
            });
            
            $tickets = Ticket::whereIn('id', $ticketIds)
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        if ($tickets->isEmpty()) {
            Notification::make()
                ->title('Export Failed')
                ->body('No tickets found to export.')
                ->warning()
                ->send();
            return;
        }

        try {
            $fileName = 'tickets_' . ($this->selectedProject?->name ?? 'export') . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
            $fileName = \Illuminate\Support\Str::slug($fileName, '_') . '.xlsx';
            $export = new TicketsExport($tickets, $selectedColumns);
            Excel::store($export, 'exports/' . $fileName, 'public');
            $downloadUrl = asset('storage/exports/' . $fileName);
            $this->js("
                fetch('{$downloadUrl}')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = '{$fileName}';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    });
            ");
            
            Notification::make()
                ->title('Export Successful')
                ->body('Your Excel file is being downloaded.')
                ->success()
                ->send();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export Failed')
                ->body('An error occurred while exporting: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Check if current user can move tickets on the board
     * Uses Filament Shield permissions to determine access
     * 
     * @return bool
     */
    public function canMoveTickets(): bool
    {
        // Check Filament Shield permission for updating tickets
        return auth()->user()->can('update_ticket');
    }
}
