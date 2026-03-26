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
                        'rejected' => 'danger',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->description(fn (WeeklyAllowanceBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
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
