<?php

namespace App\Filament\Widgets;

use App\Models\DailyTodo;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class DailyTodoOverviewWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Daily To-Do Overview';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /**
     * Check if user is in full management role (can view all todos)
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

    public static function canView(): bool
    {
        $user = auth()->user();
        return self::isManagementRole($user);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        
        return $table
            ->query(
                DailyTodo::query()
                    ->with('user')
                    ->when(self::isFullManagementRole($user), function ($query) {
                        // Full management sees all todos
                    })
                    ->when(self::isHeadDivision($user) && !self::isFullManagementRole($user), function ($query) use ($user) {
                        // Head Division sees only their division's todos
                        $query->whereHas('user', function ($q) use ($user) {
                            $q->where('division', $user->division);
                        });
                    })
                    ->when(!self::isManagementRole($user), function ($query) {
                        // Regular users see only their own
                        $query->where('user_id', auth()->id());
                    })
                    ->latest('date')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('user.division')
                    ->label('Division')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable()
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
            ])
            ->defaultSort('date', 'desc')
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
                        return User::whereNotNull('division')
                            ->distinct()
                            ->pluck('division', 'division')
                            ->toArray();
                    })
                    ->visible(fn () => self::isManagementRole(auth()->user())),
                
                Tables\Filters\Filter::make('date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from')
                            ->label('From Date')
                            ->default(today()),
                        \Filament\Forms\Components\DatePicker::make('date_until')
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
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->size('sm')
                    ->tooltip('View To-Do')
                    ->url(fn (DailyTodo $record): string => 
                        route('filament.admin.resources.daily-todos.view', $record)
                    )
                    ->openUrlInNewTab(),
            ])
            ->recordUrl(fn (DailyTodo $record) => 
                route('filament.admin.resources.daily-todos.view', $record)
            )
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No To-Do Items Found')
            ->emptyStateDescription('No daily to-do items found for the selected filters.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}

