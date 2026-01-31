<?php

namespace App\Filament\Resources;

use App\Enums\TicketFrequency;
use App\Filament\Resources\TicketTemplateResource\Pages;
use App\Filament\Resources\TicketTemplateResource\RelationManagers;
use App\Helpers\RoleHelper;
use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketTemplateResource extends Resource
{
    protected static ?string $model = TicketTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return __('Project Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Ticket Templates');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Ticket Templates');
    }

    public static function getLabel(): ?string
    {
        return __('Ticket Template');
    }

    /**
     * Control navigation visibility based on permissions.
     * Requirements: 8.3
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('view_any_ticket::template') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Template Name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\RichEditor::make('description')
                            ->label(__('Description'))
                            ->columnSpanFull(),

                        Forms\Components\Select::make('project_id')
                            ->label(__('Project'))
                            ->options(function () {
                                if (auth()->user()->hasRole(['super_admin'])) {
                                    return Project::active()->whereNull('done_at')->pluck('name', 'id')->toArray();
                                }
                                return auth()->user()->projects()->whereNull('archived_at')->whereNull('done_at')->pluck('name', 'projects.id')->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('initial_status_id', null);
                                $set('assignees', []);
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active'))
                            ->helperText(__('Only active templates will generate tickets'))
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule Configuration')
                    ->description(__('Configure when tickets should be generated'))
                    ->schema([
                        Forms\Components\Select::make('frequency')
                            ->label(__('Frequency'))
                            ->options([
                                'weekly' => __('Weekly'),
                                'monthly' => __('Monthly'),
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('day_value', null)),

                        Forms\Components\Select::make('day_value')
                            ->label(fn (callable $get) => $get('frequency') === 'weekly' ? __('Day of Week') : __('Day of Month'))
                            ->options(function (callable $get) {
                                $frequency = $get('frequency');
                                if ($frequency === 'weekly') {
                                    return [
                                        1 => __('Monday'),
                                        2 => __('Tuesday'),
                                        3 => __('Wednesday'),
                                        4 => __('Thursday'),
                                        5 => __('Friday'),
                                        6 => __('Saturday'),
                                        7 => __('Sunday'),
                                    ];
                                }
                                if ($frequency === 'monthly') {
                                    $days = [];
                                    for ($i = 1; $i <= 31; $i++) {
                                        $days[$i] = (string) $i;
                                    }
                                    return $days;
                                }
                                return [];
                            })
                            ->required()
                            ->visible(fn (callable $get) => $get('frequency') !== null)
                            ->rules([
                                fn (callable $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $frequency = $get('frequency');
                                    if ($frequency === 'weekly' && ($value < 1 || $value > 7)) {
                                        $fail(__('Day of week must be between 1 and 7'));
                                    }
                                    if ($frequency === 'monthly' && ($value < 1 || $value > 31)) {
                                        $fail(__('Day of month must be between 1 and 31'));
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('lead_time_days')
                            ->label(__('Lead Time (Days)'))
                            ->helperText(__('Number of days before the target date to create the ticket (0-30)'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(30)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Ticket Configuration')
                    ->description(__('Configure the generated ticket properties'))
                    ->schema([
                        Forms\Components\Select::make('initial_status_id')
                            ->label(__('Initial Status'))
                            ->helperText(__('The status column where generated tickets will appear'))
                            ->options(function (callable $get) {
                                $projectId = $get('project_id');
                                if (!$projectId) {
                                    return [];
                                }
                                return TicketStatus::where('project_id', $projectId)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->visible(fn (callable $get) => $get('project_id') !== null),

                        Forms\Components\Select::make('priority_id')
                            ->label(__('Priority'))
                            ->options(TicketPriority::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\Select::make('assignees')
                            ->label(__('Assignees'))
                            ->helperText(__('Users who will be assigned to generated tickets'))
                            ->multiple()
                            ->relationship(
                                name: 'assignees',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, callable $get) {
                                    $projectId = $get('project_id');
                                    if (!$projectId) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    $project = Project::find($projectId);
                                    if (!$project) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    // Filter by project members
                                    $query = $query->whereHas('projects', function ($query) use ($projectId) {
                                        $query->where('projects.id', $projectId);
                                    });

                                    // Apply role-based restrictions
                                    return RoleHelper::scopeAllowedAssignees($query);
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('project_id') !== null),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\Select::make('created_by')
                            ->label(__('Created By'))
                            ->relationship('creator', 'name')
                            ->disabled()
                            ->hiddenOn('create'),
                    ])
                    ->collapsed()
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('frequency')
                    ->label(__('Frequency'))
                    ->badge()
                    ->formatStateUsing(fn (TicketFrequency $state): string => $state->label())
                    ->color(fn (TicketFrequency $state): string => match ($state) {
                        TicketFrequency::Weekly => 'info',
                        TicketFrequency::Monthly => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('day_value')
                    ->label(__('Target Day'))
                    ->formatStateUsing(function ($state, TicketTemplate $record): string {
                        return $record->frequency->dayValueLabel($state);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('lead_time_days')
                    ->label(__('Lead Time'))
                    ->formatStateUsing(fn (int $state): string => $state . ' ' . __('days'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_generation_date')
                    ->label(__('Next Generation'))
                    ->getStateUsing(fn (TicketTemplate $record): string => $record->getNextGenerationDate()->format('d/m/Y'))
                    ->badge()
                    ->color(function (TicketTemplate $record): string {
                        $daysUntil = now()->diffInDays($record->getNextGenerationDate(), false);
                        if ($daysUntil <= 0) return 'danger';
                        if ($daysUntil <= 3) return 'warning';
                        return 'success';
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('generations_count')
                    ->counts('generations')
                    ->label(__('Generated'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label(__('Project'))
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }
                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('frequency')
                    ->label(__('Frequency'))
                    ->options([
                        'weekly' => __('Weekly'),
                        'monthly' => __('Monthly'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Active only'))
                    ->falseLabel(__('Inactive only')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (TicketTemplate $record): string => $record->is_active ? __('Deactivate') : __('Activate'))
                    ->icon(fn (TicketTemplate $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (TicketTemplate $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (TicketTemplate $record): void {
                        $record->update(['is_active' => !$record->is_active]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\GenerationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketTemplates::route('/'),
            'create' => Pages\CreateTicketTemplate::route('/create'),
            'view' => Pages\ViewTicketTemplate::route('/{record}'),
            'edit' => Pages\EditTicketTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if (!$user) {
            return $query;
        }

        // Super admin and C-Level can see all templates
        $userIsSuperAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        $userIsCLevel = class_exists('App\Helpers\RoleHelper') && RoleHelper::isCLevel($user);

        if (!$userIsSuperAdmin && !$userIsCLevel) {
            // Regular users can only see templates for projects they're members of
            $query->whereHas('project.members', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }

        return $query;
    }

    /**
     * Get the navigation badge count.
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->active()->count();
    }
}
