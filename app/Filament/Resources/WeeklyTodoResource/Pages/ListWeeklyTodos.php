<?php

namespace App\Filament\Resources\WeeklyTodoResource\Pages;

use App\Filament\Resources\WeeklyTodoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyTodos extends ListRecords
{
    protected static string $resource = WeeklyTodoResource::class;

    public ?string $statusSortOrder = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('sort_pending_first')
                    ->label('Pending → In Progress → Done')
                    ->icon('heroicon-o-arrow-down')
                    ->action(fn () => $this->setStatusSort('pending_first')),
                Actions\Action::make('sort_done_first')
                    ->label('Done → In Progress → Pending')
                    ->icon('heroicon-o-arrow-up')
                    ->action(fn () => $this->setStatusSort('done_first')),
                Actions\Action::make('sort_in_progress_first')
                    ->label('In Progress → Pending → Done')
                    ->icon('heroicon-o-arrows-right-left')
                    ->action(fn () => $this->setStatusSort('in_progress_first')),
                Actions\Action::make('clear_status_sort')
                    ->label('Clear Status Sort')
                    ->icon('heroicon-o-x-mark')
                    ->action(fn () => $this->setStatusSort(null))
                    ->visible(fn () => $this->statusSortOrder !== null),
            ])
                ->label(fn () => $this->getStatusSortLabel())
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->button(),
            Actions\CreateAction::make()
                ->label('New Weekly To-Do')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->size('lg')
                ->keyBindings(['mod+c'])
                ->tooltip('Create a new weekly to-do item (Ctrl/Cmd + C)'),
        ];
    }

    public function shouldPersistTableFiltersInSession(): bool
    {
        return true;
    }

    protected function getStatusSortLabel(): string
    {
        return match ($this->statusSortOrder) {
            'pending_first' => 'Sort: Pending First',
            'done_first' => 'Sort: Done First',
            'in_progress_first' => 'Sort: In Progress First',
            default => 'Sort by Status',
        };
    }

    public function setStatusSort(?string $order): void
    {
        $this->statusSortOrder = $order;
        session(['weekly_todo_status_sort' => $order]);
    }

    public function mount(): void
    {
        parent::mount();
        $this->statusSortOrder = session('weekly_todo_status_sort');
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();

        if ($this->statusSortOrder && $query) {
            $orderCase = match ($this->statusSortOrder) {
                'pending_first' => "CASE WHEN status = 'pending' THEN 1 WHEN status = 'in_progress' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END",
                'done_first' => "CASE WHEN status = 'done' THEN 1 WHEN status = 'in_progress' THEN 2 WHEN status = 'pending' THEN 3 ELSE 4 END",
                'in_progress_first' => "CASE WHEN status = 'in_progress' THEN 1 WHEN status = 'pending' THEN 2 WHEN status = 'done' THEN 3 ELSE 4 END",
                default => null,
            };

            if ($orderCase) {
                $query->orderByRaw($orderCase);
            }
        }

        return $query;
    }
}

