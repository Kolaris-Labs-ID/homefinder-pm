<?php

namespace App\Filament\Resources;

use App\Exports\PerformanceSummaryExport;
use App\Filament\Resources\PerformanceCycleResource\Pages;
use App\Models\Hris\PerformanceCycle;
use App\Services\Hris\PerformanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class PerformanceCycleResource extends Resource
{
    protected static ?string $model = PerformanceCycle::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Performance Cycles';

    protected static ?string $navigationGroup = 'Performance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cycle Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                PerformanceCycle::STATUS_DRAFT => 'Draft',
                                PerformanceCycle::STATUS_ACTIVE => 'Active',
                                PerformanceCycle::STATUS_CLOSED => 'Closed',
                            ])
                            ->default(PerformanceCycle::STATUS_DRAFT)
                            ->disabled()
                            ->dehydrated()
                            ->visibleOn('edit'),
                        Forms\Components\DatePicker::make('period_start_date')
                            ->label('Start Date')
                            ->required(),
                        Forms\Components\DatePicker::make('period_end_date')
                            ->label('End Date')
                            ->required()
                            ->afterOrEqual('period_start_date'),
                    ]),

                Forms\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => PerformanceCycle::STATUS_DRAFT,
                        'success' => PerformanceCycle::STATUS_ACTIVE,
                        'danger' => PerformanceCycle::STATUS_CLOSED,
                    ]),
                Tables\Columns\TextColumn::make('goals_count')
                    ->label('Goals')
                    ->counts('goals')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PerformanceCycle::STATUS_DRAFT => 'Draft',
                        PerformanceCycle::STATUS_ACTIVE => 'Active',
                        PerformanceCycle::STATUS_CLOSED => 'Closed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate Performance Cycle')
                    ->modalDescription(function (PerformanceCycle $record): string {
                        $service = app(PerformanceService::class);
                        $overlapping = $service->checkCycleOverlap($record);

                        $message = 'Are you sure you want to activate this performance cycle?';

                        if ($overlapping->isNotEmpty()) {
                            $names = $overlapping->pluck('name')->join(', ');
                            $message .= "\n\nWarning: This cycle overlaps with active cycles: {$names}";
                        }

                        return $message;
                    })
                    ->visible(fn (PerformanceCycle $record): bool => $record->isDraft())
                    ->action(function (PerformanceCycle $record): void {
                        $service = app(PerformanceService::class);
                        $service->activateCycle($record);

                        Notification::make()
                            ->title('Performance cycle activated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Close Performance Cycle')
                    ->modalDescription('Are you sure you want to close this performance cycle? Goals and reviews will no longer be modifiable.')
                    ->visible(fn (PerformanceCycle $record): bool => $record->isActive())
                    ->action(function (PerformanceCycle $record): void {
                        $service = app(PerformanceService::class);
                        $service->closeCycle($record);

                        Notification::make()
                            ->title('Performance cycle closed')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('exportPerformance')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (PerformanceCycle $record) {
                        return Excel::download(
                            new PerformanceSummaryExport($record->id),
                            'performance-summary-' . str($record->name)->slug() . '.xlsx'
                        );
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PerformanceCycle $record): bool => !$record->isClosed()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportAll')
                    ->label('Export All Performance')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(
                            new PerformanceSummaryExport(),
                            'performance-summary-all-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
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
            'index' => Pages\ListPerformanceCycles::route('/'),
            'create' => Pages\CreatePerformanceCycle::route('/create'),
            'view' => Pages\ViewPerformanceCycle::route('/{record}'),
            'edit' => Pages\EditPerformanceCycle::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::active()->count();
    }
}
