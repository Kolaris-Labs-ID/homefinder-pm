<?php

namespace App\Filament\Actions;

use App\Exports\ProjectsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

class ExportProjectsAction
{
    public static function make(): Action
    {
        return Action::make('export_projects')
            ->label('Export to Excel')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->form([
                Section::make('Select Columns to Export')
                    ->description('Choose which columns you want to include in the Excel export')
                    ->schema([
                        CheckboxList::make('columns')
                            ->label('Columns')
                            ->options((new ProjectsExport(collect(), []))->getAvailableColumns())
                            ->default(['name', 'ticket_prefix', 'owner', 'division', 'status', 'members_count', 'tickets_count', 'created_at'])
                            ->required()
                            ->minItems(1)
                            ->columns(2)
                            ->gridDirection('row'),
                    ]),
            ])
            ->action(function (array $data, $livewire) {
                // ponytail: materialises the whole filtered set in memory.
                // If project counts ever reach five figures, switch to FromQuery + WithChunkReading.
                $projects = $livewire->getFilteredSortedTableQuery()
                    ->with(['owner', 'archivedBy'])
                    ->get();

                if ($projects->isEmpty()) {
                    Notification::make()
                        ->title('Nothing to export')
                        ->body('The current tab and filters returned no projects.')
                        ->warning()
                        ->send();

                    return null;
                }

                return Excel::download(
                    new ProjectsExport($projects, $data['columns'] ?? []),
                    'projects-'.now()->format('Y-m-d-Hi').'.xlsx'
                );
            });
    }
}
