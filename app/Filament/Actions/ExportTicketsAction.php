<?php

namespace App\Filament\Actions;

use App\Exports\TicketsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ExportTicketsAction
{
    public static function make(): Action
    {
        return Action::make('export_tickets')
            ->label('Export to Excel')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->form([
                Section::make('Select Columns to Export')
                    ->description('Choose which columns you want to include in the Excel export')
                    ->schema([
                        CheckboxList::make('columns')
                            ->label('Columns')
                            ->options((new TicketsExport(collect(), []))->getAvailableColumns())
                            ->default(['uuid', 'name', 'status', 'assignee', 'due_date', 'created_at'])
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
            ])
            ->action(function (array $data, $livewire) {
                $columns = $data['columns'] ?? [];

                // ProjectBoard is a plain Page with no table; it owns its own query building.
                if (! method_exists($livewire, 'getFilteredSortedTableQuery')) {
                    return $livewire->exportTickets($columns);
                }

                // ponytail: materialises the whole filtered set in memory (~1KB/ticket).
                // If a tenant ever exports >50k tickets, switch to FromQuery + WithChunkReading.
                $tickets = $livewire->getFilteredSortedTableQuery()
                    ->with(['assignees', 'status', 'project', 'epic'])
                    ->get();

                if ($tickets->isEmpty()) {
                    Notification::make()
                        ->title('Nothing to export')
                        ->body('The current filters returned no tickets.')
                        ->warning()
                        ->send();

                    return null;
                }

                return Excel::download(
                    new TicketsExport($tickets, $columns),
                    'tickets-'.now()->format('Y-m-d-Hi').'.xlsx'
                );
            });
    }
}
