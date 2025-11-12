<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeeklyTodoResource\Pages;
use App\Filament\Resources\WeeklyTodoResource\RelationManagers;
use App\Models\WeeklyTodo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\User;
use Carbon\Carbon;

class WeeklyTodoResource extends Resource
{
    protected static ?string $model = WeeklyTodo::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Weekly To-Do List';

    protected static ?string $navigationGroup = 'To-Do Management';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Management roles always see it
        if (self::isManagementRole($user)) {
            return true;
        }
        
        // Check if user has any weekly todo permission
        return $user->can('view_any_weekly::todo') 
            || $user->can('view_weekly::todo')
            || $user->can('create_weekly::todo');
    }

    /**
     * Check if user is in full management role (can view all todos across all divisions)
     */
    private static function isFullManagementRole(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'admin', 'CEO', 'COO', 'CTO', 'HR']);
    }

    /**
     * Check if user is head division (can view their division's todos)
     */
    private static function isHeadDivision(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        
        return $user->is_division_head && $user->division;
    }

    /**
     * Check if user is in management role (either full management or head division)
     */
    private static function isManagementRole(?User $user): bool
    {
        return self::isFullManagementRole($user) || self::isHeadDivision($user);
    }

    /**
     * Get the start of the week (Monday)
     */
    private static function getStartOfWeek(?Carbon $date = null): Carbon
    {
        $date = $date ?? today();
        return $date->copy()->startOfWeek(Carbon::MONDAY);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Full management roles can see all todos
        if (self::isFullManagementRole($user)) {
            return $query;
        }

        // Head Division can see todos from their division only
        if (self::isHeadDivision($user)) {
            return $query->whereHas('user', function ($q) use ($user) {
                $q->where('division', $user->division);
            });
        }

        // Regular users can only see their own todos
        return $query->where('user_id', $user->id);
    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship(
                        name: 'user',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) use ($user) {
                            // Head Division can only select users from their division
                            if (self::isHeadDivision($user) && !self::isFullManagementRole($user)) {
                                return $query->where('division', $user->division);
                            }
                            return $query;
                        }
                    )
                    ->default(fn () => $user->id)
                    ->required(fn () => self::isManagementRole(auth()->user()))
                    ->searchable()
                    ->preload()
                    ->disabled(fn () => !auth()->user()->hasRole(['super_admin', 'admin', 'CEO', 'COO', 'CTO', 'HR']))
                    ->visible(fn () => self::isManagementRole(auth()->user()))
                    ->dehydrated(fn ($state, $operation) => $operation === 'create' ? ($state ?? auth()->id()) : $state),
                
                Forms\Components\DatePicker::make('week_start_date')
                    ->label('Week Start Date (Monday)')
                    ->default(fn () => self::getStartOfWeek())
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->helperText('Select the Monday of the week for this to-do'),
                
                Forms\Components\DatePicker::make('deadline')
                    ->label('Deadline / Due Date')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->minDate(fn ($get) => $get('week_start_date') ? \Carbon\Carbon::parse($get('week_start_date')) : today())
                    ->helperText('Optional: Set a deadline for this to-do'),
                
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
                
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'done' => 'Done',
                    ])
                    ->default('pending')
                    ->required()
                    ->native(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => self::isManagementRole(auth()->user())),
                
                Tables\Columns\TextColumn::make('user.division')
                    ->label('Division')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->visible(fn () => self::isManagementRole(auth()->user())),
                
                Tables\Columns\TextColumn::make('week_start_date')
                    ->label('Week Start Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($record) => $record->week_start_date ? 'Week of ' . $record->week_start_date->format('M d') . ' - ' . $record->week_start_date->copy()->addDays(6)->format('M d, Y') : null),
                
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Deadline')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->color(function ($record) {
                        if (!$record->deadline) return null;
                        $now = today();
                        if ($record->deadline->isPast()) return 'danger';
                        if ($record->deadline->isToday()) return 'warning';
                        if ($record->deadline->diffInDays($now) <= 3) return 'warning';
                        return null;
                    })
                    ->badge(fn ($record) => $record->deadline && $record->deadline->isPast() && $record->status !== 'done')
                    ->icon(fn ($record) => $record->deadline && $record->deadline->isPast() && $record->status !== 'done' ? 'heroicon-o-exclamation-triangle' : null)
                    ->placeholder('No deadline'),
                
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(50)
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'done' => 'Done',
                        default => 'Pending',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => self::isManagementRole(auth()->user())),
                
                Tables\Filters\SelectFilter::make('division')
                    ->label('Division')
                    ->options(function () {
                        return \App\Models\User::whereNotNull('division')
                            ->distinct()
                            ->pluck('division', 'division')
                            ->toArray();
                    })
                    ->visible(fn () => self::isManagementRole(auth()->user())),
                
                Tables\Filters\Filter::make('week_start_date')
                    ->form([
                        Forms\Components\DatePicker::make('week_from')
                            ->label('From Week Start')
                            ->default(fn () => self::getStartOfWeek()),
                        Forms\Components\DatePicker::make('week_until')
                            ->label('Until Week Start')
                            ->default(fn () => self::getStartOfWeek()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['week_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '>=', self::getStartOfWeek(Carbon::parse($date))),
                            )
                            ->when(
                                $data['week_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('week_start_date', '<=', self::getStartOfWeek(Carbon::parse($date))),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['week_from'] ?? null) {
                            $indicators[] = 'From Week: ' . self::getStartOfWeek(Carbon::parse($data['week_from']))->format('d/m/Y');
                        }
                        if ($data['week_until'] ?? null) {
                            $indicators[] = 'Until Week: ' . self::getStartOfWeek(Carbon::parse($data['week_until']))->format('d/m/Y');
                        }
                        return $indicators;
                    }),
                
                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn (Builder $query): Builder => $query->whereDate('week_start_date', self::getStartOfWeek()))
                    ->toggle(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'done' => 'Done',
                    ]),
            ])
            ->defaultSort('week_start_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => self::isManagementRole(auth()->user())),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWeeklyTodos::route('/'),
            'create' => Pages\CreateWeeklyTodo::route('/create'),
            'view' => Pages\ViewWeeklyTodo::route('/{record}'),
            'edit' => Pages\EditWeeklyTodo::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Management roles always have access
        if (self::isManagementRole($user)) {
            return true;
        }
        
        // Check for any weekly todo permission
        return $user->can('view_any_weekly::todo') 
            || $user->can('view_weekly::todo')
            || $user->can('create_weekly::todo');
    }

    public static function canCreate(): bool
    {
        // All authenticated users can create their own todos
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Check for create permission (supports both formats)
        return $user->can('create_weekly::todo') 
            || $user->can('create_weekly_todo');
    }
}

