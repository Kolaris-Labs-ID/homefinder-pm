<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Grid;
use Filament\Support\Enums\FontWeight;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('board')
                ->label('Project Board')
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(fn () => \App\Filament\Pages\ProjectBoard::getUrl(['project_id' => $this->record->id])),
            Actions\Action::make('external_access')
                ->label('External Dashboard')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->modalHeading('External Dashboard Access')
                ->modalDescription('Share these credentials with external users to access the project dashboard.')
                ->modalContent(function () {
                    $record = $this->record;
                    $externalAccess = $record->externalAccess;
                
                    if (!$externalAccess) {
                        $externalAccess = $record->generateExternalAccess();
                    }
                
                    $dashboardUrl = url('/external/' . $externalAccess->access_token);
                
                    return view('filament.components.external-access-modal', [
                        'dashboardUrl' => $dashboardUrl,
                        'password' => $externalAccess->password,
                        'lastAccessed' => $externalAccess->last_accessed_at ? $externalAccess->last_accessed_at->format('d/m/Y H:i') : null,
                        'isActive' => $externalAccess->is_active,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Project Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Project Name')
                                    ->weight(FontWeight::Bold)
                                    ->size('lg'),
                                TextEntry::make('ticket_prefix')
                                    ->label('Ticket Prefix')
                                    ->badge()
                                    ->color('primary'),
                            ]),
                        TextEntry::make('description')
                            ->label('Description')
                            ->html()
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('start_date')
                                    ->label('Start Date')
                                    ->date('d/m/Y')
                                    ->placeholder('Not set'),
                                TextEntry::make('end_date')
                                    ->label('End Date')
                                    ->date('d/m/Y')
                                    ->placeholder('Not set'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('remaining_days')
                                    ->label('Remaining Days')
                                    ->getStateUsing(function ($record): ?string {
                                        if (!$record->end_date) {
                                            return 'Not set';
                                        }
                                        return $record->remaining_days . ' days';
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => 
                                        !$record->end_date ? 'gray' :
                                        ($record->remaining_days <= 0 ? 'danger' : 
                                        ($record->remaining_days <= 7 ? 'warning' : 'success'))
                                    ),
                                TextEntry::make('pinned_date')
                                    ->label('Pinned Status')
                                    ->getStateUsing(function ($record): string {
                                        return $record->pinned_date ? 'Pinned on ' . $record->pinned_date->format('d/m/Y H:i') : 'Not pinned';
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => $record->pinned_date ? 'success' : 'gray'),
                            ]),
                    ]),
                
                Section::make('Project Statistics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('members_count')
                                    ->label('Total Members')
                                    ->getStateUsing(fn ($record) => $record->members()->count())
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('tickets_count')
                                    ->label('Total Tickets')
                                    ->getStateUsing(fn ($record) => $record->tickets()->count())
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('epics_count')
                                    ->label('Total Epics')
                                    ->getStateUsing(fn ($record) => $record->epics()->count())
                                    ->badge()
                                    ->color('warning'),
                                TextEntry::make('statuses_count')
                                    ->label('Ticket Statuses')
                                    ->getStateUsing(fn ($record) => $record->ticketStatuses()->count())
                                    ->badge()
                                    ->color('success'),
                            ]),
                    ]),
                    
                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}