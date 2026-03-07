<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;
use App\Filament\Resources\WeeklyAllowanceBatchResource\RelationManagers;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Services\Hris\PayrollApprovalService;
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
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'calculated' => 'info',
                        'pending_finance' => 'warning',
                        'pending_coo' => 'warning',
                        'rejected' => 'danger',
                        'paid' => 'success',
                        default => 'gray',
                    }),
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
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
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
                Tables\Actions\Action::make('submitForApproval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canSubmitForApproval())
                    ->requiresConfirmation()
                    ->modalHeading('Submit for Approval')
                    ->modalDescription('Are you sure you want to submit this weekly allowance batch for approval?')
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            $service->submitForApproval($record, auth()->user());

                            Notification::make()
                                ->title('Submitted for Approval')
                                ->body('The weekly allowance batch has been submitted for finance approval.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Submission Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('approveAndPay')
                    ->label('Approve & Pay')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(function (WeeklyAllowanceBatch $record) {
                        if ($record->isPaid()) {
                            return false;
                        }
                        if (! $record->canBeApprovedByFinance() && ! $record->canBeApprovedByCoo()) {
                            return false;
                        }
                        $service = app(PayrollApprovalService::class);
                        return $service->canUserActOnBatch($record, auth()->user());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Pay')
                    ->modalDescription('Are you sure you want to approve this weekly allowance batch and mark it as paid?')
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            if ($record->canBeApprovedByCoo()) {
                                $service->cooApproveAndPay($record, auth()->user());
                            } else {
                                $service->approveAndPay($record, auth()->user());
                            }

                            Notification::make()
                                ->title('Approved & Paid')
                                ->body('The weekly allowance batch has been approved and marked as paid.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Approval Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('escalateToCoo')
                    ->label('Escalate to COO')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->visible(function (WeeklyAllowanceBatch $record) {
                        if (! $record->canBeEscalatedToCoo()) {
                            return false;
                        }
                        $service = app(PayrollApprovalService::class);
                        return $service->isEscalationAvailable($record)
                            && $service->canUserActOnBatch($record, auth()->user());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Escalate to COO')
                    ->modalDescription('Are you sure you want to escalate this weekly allowance batch to the COO for approval?')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Escalation Notes')
                            ->placeholder('Optional notes for the COO...')
                            ->rows(3),
                    ])
                    ->action(function (WeeklyAllowanceBatch $record, array $data) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            $service->escalateToCoo($record, auth()->user(), $data['notes'] ?? null);

                            Notification::make()
                                ->title('Escalated to COO')
                                ->body('The weekly allowance batch has been escalated to the COO for approval.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Escalation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (WeeklyAllowanceBatch $record) {
                        if (! $record->canBeRejected()) {
                            return false;
                        }
                        $service = app(PayrollApprovalService::class);
                        return $service->canUserActOnBatch($record, auth()->user());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reject Weekly Allowance Batch')
                    ->modalDescription('Please provide a reason for rejecting this weekly allowance batch.')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Enter the reason for rejection...')
                            ->rows(3),
                    ])
                    ->action(function (WeeklyAllowanceBatch $record, array $data) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            $service->reject($record, auth()->user(), $data['rejection_reason']);

                            Notification::make()
                                ->title('Batch Rejected')
                                ->body('The weekly allowance batch has been rejected.')
                                ->warning()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Rejection Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('resubmit')
                    ->label('Resubmit')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->visible(fn (WeeklyAllowanceBatch $record) => $record->canBeResubmitted())
                    ->requiresConfirmation()
                    ->modalHeading('Resubmit for Approval')
                    ->modalDescription('Are you sure you want to resubmit this weekly allowance batch for approval?')
                    ->action(function (WeeklyAllowanceBatch $record) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            $service->resubmit($record, auth()->user());

                            Notification::make()
                                ->title('Resubmitted for Approval')
                                ->body('The weekly allowance batch has been resubmitted for finance approval.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Resubmission Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
