<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;
use App\Filament\Resources\WeeklyAllowanceBatchResource\RelationManagers;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Services\Hris\WeeklyAllowanceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WeeklyAllowanceBatchResource extends Resource
{
    protected static ?string $model = WeeklyAllowanceBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Weekly Allowance';

    protected static ?string $modelLabel = 'Weekly Allowance Batch';

    protected static ?string $pluralModelLabel = 'Weekly Allowance Batches';

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::getModel()::pending()->count();

        return $pendingCount > 0 ? (string) $pendingCount : null;
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
                            ->default(fn () => WeeklyAllowanceService::getPreviousWeekDates()['start']),
                        Forms\Components\DatePicker::make('week_end_date')
                            ->label('Week End (Friday)')
                            ->required()
                            ->default(fn () => WeeklyAllowanceService::getPreviousWeekDates()['end']),
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment Date (Tuesday)')
                            ->required()
                            ->default(fn () => WeeklyAllowanceService::getPreviousWeekDates()['payment']),
                    ])->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->color(fn (string $state): string => WeeklyAllowanceBatch::getStatusColors()[$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('total_employees')
                    ->label('Employees')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('employees_with_warnings')
                    ->label('Warnings')
                    ->alignCenter()
                    ->color('danger')
                    ->visible(fn ($record) => ($record->employees_with_warnings ?? 0) > 0),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hr_reviewed_at')
                    ->label('HR Reviewed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('finance_approved_at')
                    ->label('Finance Approved')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(WeeklyAllowanceBatch::getStatusLabels()),
            ])
            ->actions([
                // HR: Submit to HR (after generation)
                Tables\Actions\Action::make('submit_to_hr')
                    ->label('Submit for Review')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canSubmitToHr())
                    ->requiresConfirmation()
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(WeeklyAllowanceService::class);
                        $service->submitToHr($record);

                        Notification::make()
                            ->title('Submitted for HR Review')
                            ->success()
                            ->send();
                    }),

                // HR: Submit to Finance
                Tables\Actions\Action::make('submit_to_finance')
                    ->label('Submit to Finance')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canSubmitToFinance())
                    ->requiresConfirmation()
                    ->modalDescription(fn (WeeklyAllowanceBatch $record) => $record->employees_with_warnings > 0
                        ? "Warning: {$record->employees_with_warnings} employee(s) have missing bank information. Please update their data first."
                        : 'Are you sure you want to submit this batch to Finance?')
                    ->action(function (WeeklyAllowanceBatch $record) {
                        try {
                            $service = app(WeeklyAllowanceService::class);
                            $service->submitToFinance($record);

                            Notification::make()
                                ->title('Submitted to Finance')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Cannot Submit')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Finance: Approve
                Tables\Actions\Action::make('approve_finance')
                    ->label('Finance Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canApproveByFinance())
                    ->requiresConfirmation()
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(WeeklyAllowanceService::class);
                        $service->approveByFinance($record);

                        Notification::make()
                            ->title('Approved by Finance')
                            ->body('Batch sent for final approval.')
                            ->success()
                            ->send();
                    }),

                // COO/Final: Approve
                Tables\Actions\Action::make('approve_final')
                    ->label('Final Approve')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canApproveFinal())
                    ->requiresConfirmation()
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(WeeklyAllowanceService::class);
                        $service->approveFinal($record);

                        Notification::make()
                            ->title('Final Approval Complete')
                            ->body('Batch is ready for payment.')
                            ->success()
                            ->send();
                    }),

                // Mark as Paid
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canMarkAsPaid())
                    ->requiresConfirmation()
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(WeeklyAllowanceService::class);
                        $service->markAsPaid($record);

                        Notification::make()
                            ->title('Marked as Paid')
                            ->success()
                            ->send();
                    }),

                // Cancel
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canCancel())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required(),
                    ])
                    ->action(function (WeeklyAllowanceBatch $record, array $data) {
                        $service = app(WeeklyAllowanceService::class);
                        $service->cancelBatch($record, $data['reason']);

                        Notification::make()
                            ->title('Batch Cancelled')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => true),
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
            'index' => Pages\ListWeeklyAllowanceBatches::route('/'),
            'create' => Pages\CreateWeeklyAllowanceBatch::route('/create'),
            'view' => Pages\ViewWeeklyAllowanceBatch::route('/{record}'),
            'edit' => Pages\EditWeeklyAllowanceBatch::route('/{record}/edit'),
        ];
    }
}
