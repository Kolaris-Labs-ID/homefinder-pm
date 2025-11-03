<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyTodoResource\Pages;
use App\Filament\Resources\DailyTodoResource\RelationManagers;
use App\Models\DailyTodo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\User;

class DailyTodoResource extends Resource
{
    protected static ?string $model = DailyTodo::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Daily To-Do';

    protected static ?string $navigationGroup = 'Daily Management';

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
        
        // Check if user has any daily todo permission
        return $user->can('view_any_daily::todo') 
            || $user->can('view_daily::todo')
            || $user->can('create_daily::todo');
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
                
                Forms\Components\DatePicker::make('date')
                    ->label('Date')
                    ->default(today())
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false),
                
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
                
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                
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
                
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From Date')
                            ->default(today()),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('Until Date')
                            ->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['date_from'])->format('d/m/Y');
                        }
                        if ($data['date_until'] ?? null) {
                            $indicators[] = 'Until: ' . \Carbon\Carbon::parse($data['date_until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
                
                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', today()))
                    ->toggle(),
                
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'done' => 'Done',
                    ]),
            ])
            ->defaultSort('date', 'desc')
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
            'index' => Pages\ListDailyTodos::route('/'),
            'create' => Pages\CreateDailyTodo::route('/create'),
            'view' => Pages\ViewDailyTodo::route('/{record}'),
            'edit' => Pages\EditDailyTodo::route('/{record}/edit'),
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
        
        // Check for any daily todo permission
        return $user->can('view_any_daily::todo') 
            || $user->can('view_daily::todo')
            || $user->can('create_daily::todo');
    }

    public static function canCreate(): bool
    {
        // All authenticated users can create their own todos
        if (!auth()->check()) {
            return false;
        }
        
        $user = auth()->user();
        
        // Check for create permission (supports both formats)
        return $user->can('create_daily::todo') 
            || $user->can('create_daily_todo');
    }
}
