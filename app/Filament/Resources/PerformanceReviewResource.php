<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerformanceReviewResource\Pages;
use App\Models\Hris\Employee;
use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceReview;
use App\Services\Hris\PerformanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PerformanceReviewResource extends Resource
{
    protected static ?string $model = PerformanceReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Performance Reviews';

    protected static ?string $navigationGroup = 'Performance';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Review Assignment')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('cycle_id')
                            ->label('Performance Cycle')
                            ->relationship('cycle', 'name', fn ($query) => $query->whereIn('status', [
                                PerformanceCycle::STATUS_DRAFT,
                                PerformanceCycle::STATUS_ACTIVE,
                            ]))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('reviewer_id')
                            ->label('Reviewer')
                            ->relationship('reviewer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Forms\Components\Section::make('Review Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('overall_score')
                            ->label('Overall Score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText(function (Get $get): string {
                                $employeeId = $get('employee_id');
                                $cycleId = $get('cycle_id');

                                if (!$employeeId || !$cycleId) {
                                    return 'Select employee and cycle to calculate score from goals';
                                }

                                $employee = Employee::find($employeeId);
                                $cycle = PerformanceCycle::find($cycleId);

                                if (!$employee || !$cycle) {
                                    return 'Invalid employee or cycle';
                                }

                                $service = app(PerformanceService::class);
                                $calculatedScore = $service->calculateOverallScore($employee, $cycle);

                                return "Calculated from goals: " . number_format($calculatedScore, 2);
                            })
                            ->live(debounce: 500),
                        Forms\Components\Placeholder::make('submitted_status')
                            ->label('Status')
                            ->content(fn (?PerformanceReview $record): string =>
                                $record?->isSubmitted() ? 'Submitted' : 'Pending'
                            )
                            ->visibleOn('edit'),
                        Forms\Components\DateTimePicker::make('submitted_at')
                            ->label('Submitted At')
                            ->disabled()
                            ->visibleOn('edit'),
                    ]),

                Forms\Components\Section::make('Comments')
                    ->schema([
                        Forms\Components\Textarea::make('comments')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
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
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('overall_score')
                    ->label('Score')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 60 => 'info',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\IconColumn::make('submitted_at')
                    ->label('Submitted')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn (PerformanceReview $record): bool => $record->isSubmitted()),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not submitted')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('reviewer')
                    ->relationship('reviewer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('submitted')
                    ->label('Submitted Status')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('submitted_at'),
                        false: fn ($query) => $query->whereNull('submitted_at'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Performance Review')
                    ->modalDescription('Are you sure you want to submit this review? This action cannot be undone.')
                    ->visible(fn (PerformanceReview $record): bool =>
                        !$record->isSubmitted() && !$record->cycle->isClosed()
                    )
                    ->action(function (PerformanceReview $record): void {
                        $service = app(PerformanceService::class);
                        $service->submitReview($record);

                        Notification::make()
                            ->title('Review submitted')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('calculate_score')
                    ->label('Calculate Score')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->visible(fn (PerformanceReview $record): bool =>
                        !$record->isSubmitted() && !$record->cycle->isClosed()
                    )
                    ->action(function (PerformanceReview $record): void {
                        $service = app(PerformanceService::class);
                        $score = $service->calculateOverallScore($record->employee, $record->cycle);

                        $record->update(['overall_score' => $score]);

                        Notification::make()
                            ->title('Score calculated')
                            ->body("Overall score: " . number_format($score, 2))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PerformanceReview $record): bool =>
                        !$record->isSubmitted() && !$record->cycle->isClosed()
                    ),
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
            'index' => Pages\ListPerformanceReviews::route('/'),
            'create' => Pages\CreatePerformanceReview::route('/create'),
            'view' => Pages\ViewPerformanceReview::route('/{record}'),
            'edit' => Pages\EditPerformanceReview::route('/{record}/edit'),
        ];
    }
}
