<?php

namespace App\Filament\Widgets;

use App\Models\WeeklyTodo;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UserWeeklyTodoWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'My Weekly To-Do List';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    public ?WeeklyTodo $weeklyTodo = null;
    
    public bool $showCreateModal = false;

    /**
     * Get the start of the week (Monday)
     */
    private static function getStartOfWeek(?Carbon $date = null): Carbon
    {
        $date = $date ?? today();
        return $date->copy()->startOfWeek(Carbon::MONDAY);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WeeklyTodo::query()
                    ->where('user_id', auth()->id())
                    ->latest('week_start_date')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('week_start_date')
                    ->label('Week Start Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->weight('medium')
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
                
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->toggleable(),
                
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
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('New Weekly To-Do')
                    ->icon('heroicon-o-plus')
                    ->form([
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
                            ->minDate(fn ($get) => $get('week_start_date') ? Carbon::parse($get('week_start_date')) : today())
                            ->helperText('Optional: Set a deadline for this to-do'),
                        
                        Forms\Components\TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                        
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
                    ])
                    ->action(function (array $data) {
                        WeeklyTodo::create([
                            'user_id' => auth()->id(),
                            'week_start_date' => self::getStartOfWeek(Carbon::parse($data['week_start_date'])),
                            'deadline' => isset($data['deadline']) ? Carbon::parse($data['deadline']) : null,
                            'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                            'status' => $data['status'],
                        ]);
                        
                        Notification::make()
                            ->title('Weekly To-Do Created')
                            ->success()
                            ->send();
                        
                        $this->dispatch('$refresh');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('')
                    ->icon('heroicon-o-pencil')
                    ->size('sm')
                    ->tooltip('Edit')
                    ->form(function (WeeklyTodo $record) {
                        return [
                            Forms\Components\DatePicker::make('week_start_date')
                                ->label('Week Start Date (Monday)')
                                ->default($record->week_start_date)
                                ->required()
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->helperText('Select the Monday of the week for this to-do'),
                            
                            Forms\Components\DatePicker::make('deadline')
                                ->label('Deadline / Due Date')
                                ->default($record->deadline)
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->minDate(fn ($get) => $get('week_start_date') ? Carbon::parse($get('week_start_date')) : today())
                                ->helperText('Optional: Set a deadline for this to-do'),
                            
                            Forms\Components\TextInput::make('title')
                                ->label('Title')
                                ->default($record->title)
                                ->required()
                                ->maxLength(255),
                            
                            Forms\Components\Textarea::make('description')
                                ->label('Description')
                                ->default($record->description)
                                ->rows(3),
                            
                            Forms\Components\Select::make('status')
                                ->label('Status')
                                ->options([
                                    'pending' => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'done' => 'Done',
                                ])
                                ->default($record->status)
                                ->required()
                                ->native(false),
                        ];
                    })
                    ->action(function (WeeklyTodo $record, array $data) {
                        $data['week_start_date'] = self::getStartOfWeek(Carbon::parse($data['week_start_date']));
                        if (isset($data['deadline']) && $data['deadline']) {
                            $data['deadline'] = Carbon::parse($data['deadline']);
                        } else {
                            $data['deadline'] = null;
                        }
                        $record->update($data);
                        
                        Notification::make()
                            ->title('Weekly To-Do Updated')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->recordUrl(fn (WeeklyTodo $record) => 
                route('filament.admin.resources.weekly-todos.view', $record)
            )
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No Weekly To-Do Items Found')
            ->emptyStateDescription('You haven\'t created any weekly to-do items yet. Click "New Weekly To-Do" to get started!')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}

