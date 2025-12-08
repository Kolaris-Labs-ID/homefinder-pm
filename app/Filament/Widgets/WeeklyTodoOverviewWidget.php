<?php

namespace App\Filament\Widgets;

use App\Models\WeeklyTodo;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class WeeklyTodoOverviewWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Weekly To-Do List Overview';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /**
     * Status filter property for filtering todos by status.
     * Reactive for Livewire updates via wire:model.live
     *
     * @var string
     */
    public string $statusFilter = 'all';

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

    /**
     * Get the start of the week (Monday)
     */
    private static function getStartOfWeek(?Carbon $date = null): Carbon
    {
        $date = $date ?? today();
        return $date->copy()->startOfWeek(Carbon::MONDAY);
    }

    /**
     * Get the authenticated user
     *
     * @return User|null
     */
    private static function getAuthUser(): ?User
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    public static function canView(): bool
    {
        $user = self::getAuthUser();
        return self::isManagementRole($user);
    }

    public function table(Table $table): Table
    {
        $user = self::getAuthUser();

        return $table
            ->query(
                WeeklyTodo::query()
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
                        /** @var int|string|null $userId */
                        $userId = auth()->id();
                        if ($userId) {
                            $query->where('user_id', $userId);
                        }
                    })
                    ->when($this->statusFilter !== 'all', function ($query) {
                        // Apply status filter when not 'all'
                        $query->where('status', $this->statusFilter);
                    })
                    ->latest('week_start_date')
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
                    ->visible(function () {
                        return self::isManagementRole(self::getAuthUser());
                    }),

                Tables\Columns\TextColumn::make('week_start_date')
                    ->label('Week Start Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($record) => $record->week_start_date ? 'Week of ' . $record->week_start_date->format('M d') . ' - ' . $record->week_start_date->copy()->addDays(6)->format('M d, Y') : null),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Deadline')
                    ->formatStateUsing(function ($state) {
                        if (!$state) {
                            return null;
                        }
                        try {
                            // Handle both Carbon instance and string
                            $date = $state instanceof \Carbon\Carbon ? $state : \Carbon\Carbon::parse($state);
                            return $date->format('l, d/m/Y'); // l = Full day name (Monday, Tuesday, etc.)
                        } catch (\Exception $e) {
                            return $state instanceof \Carbon\Carbon ? $state->format('d/m/Y') : $state;
                        }
                    })
                    ->sortable()
                    ->searchable()
                    ->badge(function ($record) {
                        if (!$record->deadline) {
                            return null;
                        }

                        try {
                            if ($record->deadline->isPast() && $record->status !== 'done') {
                                return 'Overdue';
                            }

                            if ($record->deadline->isToday()) {
                                return 'Deadline';
                            }
                        } catch (\Exception $e) {
                            return null;
                        }

                        return null;
                    })
                    ->color(function ($record) {
                        if (!$record->deadline) {
                            return null;
                        }

                        try {
                            // Badge color when badge is shown
                            if ($record->deadline->isPast() && $record->status !== 'done') {
                                return 'danger';
                            }

                            if ($record->deadline->isToday()) {
                                return 'warning';
                            }

                            // Text color for dates without badges
                            $now = today();
                            if ($record->deadline->isPast()) return 'danger';
                            if ($record->deadline->isToday()) return 'warning';
                            if ($record->deadline->diffInDays($now) <= 3) return 'warning';
                        } catch (\Exception $e) {
                            return null;
                        }

                        return null;
                    })
                    ->icon(function ($record) {
                        if (!$record->deadline) return null;
                        try {
                            return $record->deadline->isPast() && $record->status !== 'done' ? 'heroicon-o-exclamation-triangle' : null;
                        } catch (\Exception $e) {
                            return null;
                        }
                    })
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
            ])
            ->defaultSort('week_start_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(function () {
                        return self::isManagementRole(self::getAuthUser());
                    }),

                Tables\Filters\Filter::make('division')
                    ->label('Division')
                    ->form([
                        \Filament\Forms\Components\Select::make('division')
                            ->label('Division')
                            ->options(function () {
                                return User::whereNotNull('division')
                                    ->distinct()
                                    ->pluck('division', 'division')
                                    ->toArray();
                            })
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['division']) || $data['division'] === null) {
                            return $query;
                        }

                        return $query->whereHas('user', function (Builder $q) use ($data) {
                            $q->where('division', $data['division']);
                        });
                    })
                    ->visible(function () {
                        return self::isManagementRole(self::getAuthUser());
                    }),

                Tables\Filters\Filter::make('week_start_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('week_from')
                            ->label('From Week Start')
                            ->default(fn () => self::getStartOfWeek()),
                        \Filament\Forms\Components\DatePicker::make('week_until')
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
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-eye')
                    ->size('sm')
                    ->tooltip('View Weekly To-Do')
                    ->url(fn (WeeklyTodo $record): string =>
                        route('filament.admin.resources.weekly-todos.view', $record)
                    )
                    ->openUrlInNewTab(),
            ])
            ->recordUrl(fn (WeeklyTodo $record) =>
                route('filament.admin.resources.weekly-todos.view', $record)
            )
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No Weekly To-Do Items Found')
            ->emptyStateDescription('No weekly to-do items found for the selected filters.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}


