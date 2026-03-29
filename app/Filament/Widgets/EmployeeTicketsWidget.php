<?php

namespace App\Filament\Widgets;

use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EmployeeTicketsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Employee Tickets Status';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 'full',
        'lg' => 'full',
        'xl' => 'full',
    ];

    protected static ?int $sort = 2; // Displayed below primary metrics

    protected static bool $isLazy = false;

    /**
     * Check if user is in full management role (can view all employees)
     */
    private static function isFullManagementRole(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'CEO', 'COO', 'CTO', 'HR']);
    }

    /**
     * Check if user is head division (can view their division's employees)
     */
    private static function isHeadDivision(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->is_division_head && $user->division;
    }

    /**
     * Get the authenticated user
     */
    private static function getAuthUser(): ?User
    {
        /** @var \Illuminate\Contracts\Auth\Authenticatable|null $user */
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    public static function canView(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $user = self::getAuthUser();

        $query = User::query()
            ->whereHas('assignedTickets', function (Builder $q) {
                $q->whereNull('archived_at');
            })
            ->whereHas('projects')
            ->when(self::isFullManagementRole($user), function (Builder $query) {
                // Management sees all
            })
            ->when(self::isHeadDivision($user) && !self::isFullManagementRole($user), function (Builder $query) use ($user) {
                // Head Division sees only their division
                $query->where('division', $user->division);
            })
            ->when(!self::isFullManagementRole($user) && !self::isHeadDivision($user), function (Builder $query) use ($user) {
                // Regular users see only their own row
                if ($user) {
                    $query->where('id', $user->id);
                }
            })
            ->withCount([
                'assignedTickets as todo_tickets_count' => function (Builder $q) {
                    $q->whereNull('archived_at')
                      ->whereHas('status', function (Builder $sq) {
                          $sq->where(function($qq) {
                              $qq->where('name', 'like', '%todo%')
                                 ->orWhere('name', 'like', '%to do%')
                                 ->orWhere('name', 'like', '%pending%')
                                 ->orWhere('name', 'like', '%open%');
                          });
                      });
                },
                'assignedTickets as in_progress_tickets_count' => function (Builder $q) {
                    $q->whereNull('archived_at')
                      ->whereHas('status', function (Builder $sq) {
                          $sq->where(function($qq) {
                              $qq->where('name', 'like', '%progress%')
                                 ->orWhere('name', 'like', '%doing%')
                                 ->orWhere('name', 'like', '%development%');
                          });
                      });
                },
                'assignedTickets as review_tickets_count' => function (Builder $q) {
                    $q->whereNull('archived_at')
                      ->whereHas('status', function (Builder $sq) {
                          $sq->where(function($qq) {
                              $qq->where('name', 'like', '%review%')
                                 ->orWhere('name', 'like', '%testing%')
                                 ->orWhere('name', 'like', '%qa%');
                          });
                      });
                },
                'assignedTickets as done_tickets_count' => function (Builder $q) {
                    $q->whereNull('archived_at')
                      ->whereHas('status', function (Builder $sq) {
                          $sq->where('is_completed', true)
                             ->orWhere('name', 'like', '%done%')
                             ->orWhere('name', 'like', '%completed%')
                             ->orWhere('name', 'like', '%closed%');
                      });
                },
                'assignedTickets as overdue_tickets_count' => function (Builder $q) {
                    $q->whereNull('archived_at')
                      ->whereNotNull('due_date')
                      ->whereDate('due_date', '<', Carbon::today())
                      ->whereHas('status', function (Builder $sq) {
                          $sq->where('is_completed', false)
                             ->where('name', 'not like', '%done%')
                             ->where('name', 'not like', '%completed%')
                             ->where('name', 'not like', '%closed%');
                      });
                }
            ]);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Employee Name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),
                
                Tables\Columns\TextColumn::make('division')
                    ->label('Division')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('todo_tickets_count')
                    ->label('To Do')
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('in_progress_tickets_count')
                    ->label('In Progress')
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('review_tickets_count')
                    ->label('Review')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('done_tickets_count')
                    ->label('Done')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('overdue_tickets_count')
                    ->label('Overdue')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignCenter(),
            ])
            ->defaultSort('in_progress_tickets_count', 'desc')
            ->paginated([10, 25, 50])
            ->striped()
            ->emptyStateHeading('No Employee Tickets Found')
            ->emptyStateDescription('There are no employees with active tickets at the moment.')
            ->emptyStateIcon('heroicon-o-ticket');
    }
}
