<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimePayBatchResource\Pages;
use App\Filament\Resources\OvertimePayBatchResource\RelationManagers;
use App\Models\Hris\OvertimePayBatch;
use App\Services\Hris\OvertimePayService;
use App\Services\Hris\PayrollApprovalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OvertimePayBatchResource extends Resource
{
    protected static ?string $model = OvertimePayBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Overtime Pay';

    protected static ?string $modelLabel = 'Overtime Pay Batch';

    protected static ?string $pluralModelLabel = 'Overtime Pay Batches';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Week Information')
                    ->schema([
                        Forms\Components\DatePicker::make('week_start_date')
                            ->label('Week Start (Monday)')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['start']),
                        Forms\Components\DatePicker::make('week_end_date')
                            ->label('Week End (Sunday)')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['end']),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date (Tuesday)')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['payment']),
                    ])->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->visibleToUser(auth()->user()))
            ->columns([
                Tables\Columns\TextColumn::make('week_label')
                    ->label('Week Period')
                    ->searchable(false),
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => OvertimePayBatch::getStatusColors()[$state] ?? 'gray')
                    ->description(fn (OvertimePayBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
                Tables\Columns\TextColumn::make('total_employees')
                    ->label('Employees')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('employees_with_warnings')
                    ->label('Warnings')
                    ->alignCenter()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OvertimePayBatch::getStatusLabels()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generateBatch')
                    ->label('Generate Batch')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('week_start_date')
                            ->label('Week Start (Monday)')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['start']),
                        Forms\Components\DatePicker::make('week_end_date')
                            ->label('Week End (Sunday)')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['end']),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->required()
                            ->default(fn () => OvertimePayService::getPreviousWeekDates()['payment']),
                    ])
                    ->action(function (array $data) {
                        $service = app(OvertimePayService::class);
                        try {
                            $batch = $service->generateBatch(
                                \Carbon\Carbon::parse($data['week_start_date']),
                                \Carbon\Carbon::parse($data['week_end_date']),
                                \Carbon\Carbon::parse($data['payment_date'])
                            );
                            Notification::make()
                                ->title("Batch generated: {$batch->total_employees} employee(s), Rp ".number_format($batch->total_amount, 0, ',', '.'))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Generation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('submitForApproval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (OvertimePayBatch $record) => $record->canSubmitForApproval())
                    ->requiresConfirmation()
                    ->action(function (OvertimePayBatch $record) {
                        try {
                            app(PayrollApprovalService::class)->submitForApproval($record, auth()->user());
                            Notification::make()->title('Submitted for Approval')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (OvertimePayBatch $record) => ! $record->isLocked())
                    ->requiresConfirmation()
                    ->modalHeading('Recalculate Overtime Pay')
                    ->modalDescription('Re-price every overtime session in this batch from the salary structure effective on each session\'s work date. Use after a backdated salary change. Batches in finance approval or already paid cannot be recalculated.')
                    ->action(function (OvertimePayBatch $record) {
                        try {
                            app(OvertimePayService::class)->recalculateBatch($record);

                            Notification::make()
                                ->title('Batch Recalculated')
                                ->body('Every session was re-priced from the salary structure effective on its work date.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Recalculation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (OvertimePayBatch $record) => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimePayBatches::route('/'),
            'create' => Pages\CreateOvertimePayBatch::route('/create'),
            'view' => Pages\ViewOvertimePayBatch::route('/{record}'),
            'edit' => Pages\EditOvertimePayBatch::route('/{record}/edit'),
        ];
    }
}
