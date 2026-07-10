<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerformanceGoalResource\Pages;
use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceGoal;
use App\Services\Hris\PerformanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PerformanceGoalResource extends Resource
{
    protected static ?string $model = PerformanceGoal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Performance Goals';

    protected static ?string $navigationGroup = 'Performance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Goal Assignment')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'full_name', fn ($query) => $query->active())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('cycle_id')
                            ->label('Performance Cycle')
                            ->relationship('cycle', 'name', fn ($query) => $query->whereIn('status', [
                                PerformanceCycle::STATUS_DRAFT,
                                PerformanceCycle::STATUS_ACTIVE,
                            ]))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Forms\Components\Section::make('Goal Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('weight')
                            ->label('Weight (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText(function (Get $get, ?PerformanceGoal $record): string {
                                $employeeId = $get('employee_id');
                                $cycleId = $get('cycle_id');

                                if (! $employeeId || ! $cycleId) {
                                    return 'Select employee and cycle to see available weight';
                                }

                                $service = app(PerformanceService::class);
                                $currentWeight = $service->getTotalGoalWeight(
                                    (int) $employeeId,
                                    (int) $cycleId,
                                    $record?->id
                                );
                                $available = 100 - $currentWeight;

                                return "Current total: {$currentWeight}%, Available: {$available}%";
                            })
                            ->live(debounce: 500),
                        Forms\Components\TextInput::make('target_value')
                            ->label('Target Value')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                    ]),

                Forms\Components\Section::make('Progress')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('actual_value')
                            ->label('Actual Value')
                            ->numeric()
                            ->minValue(0)
                            ->disabled(),
                        Forms\Components\TextInput::make('score')
                            ->label('Score')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\Placeholder::make('progress')
                            ->label('Progress')
                            ->content(fn (?PerformanceGoal $record): string => $record ? number_format($record->progress_percentage, 1).'%' : '0%'
                            ),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cycle.name')
                    ->label('Cycle')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_value')
                    ->label('Target')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_value')
                    ->label('Actual')
                    ->numeric()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->suffix('%')
                    ->formatStateUsing(fn ($state): string => number_format($state, 1))
                    ->color(fn ($state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 75 => 'info',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee')
                    ->relationship('employee', 'full_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('cycle')
                    ->relationship('cycle', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('update_progress')
                    ->label('Update Progress')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('actual_value')
                            ->label('Actual Value')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(fn (PerformanceGoal $record): ?float => $record->actual_value),
                    ])
                    ->visible(fn (PerformanceGoal $record): bool => ! $record->cycle->isClosed())
                    ->action(function (PerformanceGoal $record, array $data): void {
                        $service = app(PerformanceService::class);
                        $service->updateGoalProgress($record, (float) $data['actual_value']);

                        Notification::make()
                            ->title('Goal progress updated')
                            ->body("Score: {$record->score}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PerformanceGoal $record): bool => ! $record->cycle->isClosed()),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerformanceGoals::route('/'),
            'create' => Pages\CreatePerformanceGoal::route('/create'),
            'view' => Pages\ViewPerformanceGoal::route('/{record}'),
            'edit' => Pages\EditPerformanceGoal::route('/{record}/edit'),
        ];
    }
}
