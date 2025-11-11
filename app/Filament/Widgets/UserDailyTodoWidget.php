<?php

namespace App\Filament\Widgets;

use App\Models\DailyTodo;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UserDailyTodoWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'My Daily To-Do';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    public ?DailyTodo $dailyTodo = null;
    
    public bool $showCreateModal = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DailyTodo::query()
                    ->where('user_id', auth()->id())
                    ->latest('date')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),
                
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
            ->defaultSort('date', 'desc')
            ->filters([
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
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label('New To-Do')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->default(today())
                            ->required()
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        
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
                        DailyTodo::create([
                            'user_id' => auth()->id(),
                            'date' => $data['date'],
                            'title' => $data['title'],
                            'description' => $data['description'] ?? null,
                            'status' => $data['status'],
                        ]);
                        
                        Notification::make()
                            ->title('To-Do Created')
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
                    ->form(function (DailyTodo $record) {
                        return [
                            Forms\Components\DatePicker::make('date')
                                ->label('Date')
                                ->default($record->date)
                                ->required()
                                ->displayFormat('d/m/Y')
                                ->native(false),
                            
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
                    ->action(function (DailyTodo $record, array $data) {
                        $record->update($data);
                        
                        Notification::make()
                            ->title('To-Do Updated')
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\DeleteAction::make(),
            ])
            ->recordUrl(fn (DailyTodo $record) => 
                route('filament.admin.resources.daily-todos.view', $record)
            )
            ->paginated([10, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No To-Do Items Found')
            ->emptyStateDescription('You haven\'t created any daily to-do items yet. Click "New To-Do" to get started!')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}

