<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ImportTicketsAction;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use App\Models\User;
use App\Helpers\RoleHelper;
use App\Services\ProjectArchiveService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Project Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Project');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Projects');
    }

    public static function getLabel(): ?string
    {
        return __('Project');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\RichEditor::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ticket_prefix')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Forms\Components\Toggle::make('create_default_statuses')
                    ->label('Use Default Ticket Statuses')
                    ->helperText('Create standard Backlog, To Do, In Progress, Review, and Done statuses automatically')
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateProject),

                Forms\Components\Select::make('members')
                    ->label('Project Members')
                    ->helperText('Select users to add as project members. You can also add members later.')
                    ->multiple()
                    ->relationship(
                        name: 'members',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) {
                            try {
                                if (auth()->check()) {
                                    return RoleHelper::scopeAllowedAssignees($query);
                                }
                                return $query;
                            } catch (\Exception $e) {
                                \Log::error('Error in members modifyQueryUsing: ' . $e->getMessage());
                                return $query;
                            }
                        }
                    )
                    ->searchable(['name', 'email'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->name} ({$record->email})")
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateProject)
                    ->hidden(fn ($livewire) => !($livewire instanceof Pages\CreateProject))
                    ->dehydrated(fn ($livewire) => $livewire instanceof Pages\CreateProject)
                    ->disabled(fn ($livewire) => !($livewire instanceof Pages\CreateProject)),

                Forms\Components\Toggle::make('is_pinned')
                    ->label('Pin Project')
                    ->helperText('Pinned projects will appear in the dashboard timeline')
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('pinned_date', now());
                        } else {
                            $set('pinned_date', null);
                        }
                    })
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get) {
                        $component->state(!is_null($get('pinned_date')));
                    }),
                Forms\Components\DateTimePicker::make('pinned_date')
                    ->label('Pinned Date')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn ($get) => $get('is_pinned'))
                    ->dehydrated(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Project $record): ?string =>
                        $record->is_done
                            ? ($record->is_auto_done ? '✓ Auto-completed' : '✓ Manually completed')
                            : null
                    ),
                // Done status badge - Requirements: 2.2
                Tables\Columns\TextColumn::make('done_status')
                    ->label('Status')
                    ->getStateUsing(fn (Project $record): ?string =>
                        $record->is_done
                            ? ($record->is_auto_done ? 'Auto Done' : 'Done')
                            : null
                    )
                    ->badge()
                    ->color(fn (Project $record): ?string =>
                        $record->is_done
                            ? ($record->is_auto_done ? 'info' : 'success')
                            : null
                    )
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ticket_prefix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->getStateUsing(function (Project $record): string {
                        return $record->progress_percentage . '%';
                    })
                    ->badge()
                    ->color(fn (Project $record): string =>
                        $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 75 ? 'info' :
                        ($record->progress_percentage >= 50 ? 'warning' :
                        ($record->progress_percentage >= 25 ? 'gray' : 'danger')))
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_days')
                    ->label('Remaining Days')
                    ->getStateUsing(function (Project $record): ?string {
                        if (!$record->end_date) {
                            return null;
                        }

                        return $record->remaining_days . ' days';
                    })
                    ->badge()
                    ->color(fn (Project $record): string =>
                        !$record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' :
                        ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),
                Tables\Columns\ToggleColumn::make('is_pinned')
                    ->label('Pinned')
                    ->updateStateUsing(function ($record, $state) {
                        // Gunakan method pin/unpin yang sudah ada di model
                        if ($state) {
                            $record->pin();
                        } else {
                            $record->unpin();
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('members_count')
                    ->counts('members')
                    ->label('Members'),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label('Tickets'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Archive date column - visible in archived tab
                // Requirements: 5.2
                Tables\Columns\TextColumn::make('archived_at')
                    ->label('Archived Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Archived by user column - visible in archived tab
                // Requirements: 5.2
                Tables\Columns\TextColumn::make('archivedBy.name')
                    ->label('Archived By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Archive action - visible for active (non-archived) projects
                // Requirements: 1.1, 6.3
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Project')
                    ->modalDescription('Are you sure you want to archive this project? It will be moved to the Archived tab.')
                    ->modalSubmitActionLabel('Yes, archive it')
                    ->visible(fn (Project $record): bool => !$record->is_archived && auth()->user()->can('archive', $record))
                    ->action(function (Project $record): void {
                        $service = new ProjectArchiveService();
                        $service->archive($record, auth()->user());

                        Notification::make()
                            ->title('Project archived')
                            ->body("Project '{$record->name}' has been archived successfully.")
                            ->success()
                            ->actions([
                                NotificationAction::make('undo')
                                    ->label('Undo')
                                    ->color('gray')
                                    ->dispatch('restoreProject', ['projectId' => $record->id])
                                    ->close(),
                                NotificationAction::make('view')
                                    ->label('View Archived')
                                    ->url(static::getUrl('index', ['activeTab' => 'archived']))
                                    ->close(),
                            ])
                            ->persistent()
                            ->send();
                    }),
                // Restore action - visible for archived projects
                // Requirements: 4.1, 6.3
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Project')
                    ->modalDescription('Are you sure you want to restore this project? It will be moved back to the Active tab.')
                    ->modalSubmitActionLabel('Yes, restore it')
                    ->visible(fn (Project $record): bool => $record->is_archived && auth()->user()->can('restoreArchived', $record))
                    ->action(function (Project $record): void {
                        $service = new ProjectArchiveService();
                        $service->restore($record);

                        Notification::make()
                            ->title('Project restored')
                            ->body("Project '{$record->name}' has been restored successfully.")
                            ->success()
                            ->actions([
                                NotificationAction::make('view')
                                    ->label('View Project')
                                    ->url(static::getUrl('view', ['record' => $record->id]))
                                    ->close(),
                            ])
                            ->duration(5000)
                            ->send();
                    }),
                // Mark as Done / Unmark Done toggle action
                // Requirements: 2.1, 2.3
                Tables\Actions\Action::make('toggleDone')
                    ->label(fn (Project $record): string => $record->is_done ? 'Unmark Done' : 'Mark as Done')
                    ->icon(fn (Project $record): string => $record->is_done ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Project $record): string => $record->is_done ? 'gray' : 'success')
                    ->visible(fn (Project $record): bool => !$record->is_archived)
                    ->action(function (Project $record): void {
                        $service = new ProjectArchiveService();

                        if ($record->is_done) {
                            $service->unmarkDone($record);
                            Notification::make()
                                ->title('Done status removed')
                                ->body("Project '{$record->name}' is no longer marked as done.")
                                ->success()
                                ->send();
                        } else {
                            $service->markAsDone($record, false);
                            Notification::make()
                                ->title('Project marked as done')
                                ->body("Project '{$record->name}' has been marked as done.")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TicketStatusesRelationManager::class,
            RelationManagers\MembersRelationManager::class,
            RelationManagers\EpicsRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view' => Pages\ViewProject::route('/{record}'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            // Hapus baris ini: 'gantt-chart' => Pages\ProjectGanttChart::route('/gantt-chart'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (! $userIsSuperAdmin) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }

    /**
     * Get the navigation badge count.
     * Shows only active (non-archived) projects count.
     * Requirements: 5.4
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->active()->count();
    }
}
