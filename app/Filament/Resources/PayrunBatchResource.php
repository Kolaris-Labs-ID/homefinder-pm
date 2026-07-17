<?php

namespace App\Filament\Resources;

use App\Exceptions\Hris\PayrollCoverageException;
use App\Exports\PayrollRecordsExport;
use App\Filament\Resources\PayrunBatchResource\Pages;
use App\Filament\Resources\PayrunBatchResource\RelationManagers;
use App\Models\Hris\PayrunBatch;
use App\Services\Hris\PayrollApprovalService;
use App\Services\Hris\PayrollCalculationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class PayrunBatchResource extends Resource
{
    protected static ?string $model = PayrunBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Period Information')
                    ->schema([
                        Forms\Components\DatePicker::make('period_start_date')
                            ->label('Period Start')
                            ->required(),
                        Forms\Components\DatePicker::make('period_end_date')
                            ->label('Period End')
                            ->required()
                            ->afterOrEqual('period_start_date'),
                        Forms\Components\DatePicker::make('pay_date')
                            ->label('Pay Date')
                            ->required()
                            ->afterOrEqual('period_end_date'),
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
                Tables\Columns\TextColumn::make('period_label')
                    ->label('Period')
                    ->searchable(false),
                Tables\Columns\TextColumn::make('pay_date')
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
                    ->description(fn (PayrunBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
                Tables\Columns\TextColumn::make('payroll_records_count')
                    ->label('Employees')
                    ->counts('payrollRecords'),
                Tables\Columns\TextColumn::make('total_net_income')
                    ->label('Total Net Income')
                    ->money('IDR')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('calculated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(PayrunBatch::getStatusLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('calculate')
                    ->label('Calculate')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->visible(fn (PayrunBatch $record) => $record->canCalculate())
                    ->requiresConfirmation()
                    ->action(function (PayrunBatch $record) {
                        $service = app(PayrollCalculationService::class);

                        try {
                            $results = $service->calculatePayrun($record);
                        } catch (PayrollCoverageException $e) {
                            $gaps = collect($e->gaps)
                                ->map(fn (array $g) => ($g['employee_name'] ?? 'Employee')." (#{$g['employee_id']}, needs coverage from {$g['first_working_day']})")
                                ->join(', ');

                            Notification::make()
                                ->title('Calculation Failed')
                                ->body("No salary structure covers: {$gaps}. Add a salary structure effective on/before that date, or deactivate the employee.")
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Calculation Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Payrun Calculated')
                            ->body("Processed: {$results['processed']}, Skipped: {$results['skipped']}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('submitForApproval')
                    ->label('Submit for Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (PayrunBatch $record) => $record->canSubmitForApproval())
                    ->requiresConfirmation()
                    ->modalHeading('Submit for Approval')
                    ->modalDescription('Are you sure you want to submit this payrun batch for approval?')
                    ->action(function (PayrunBatch $record) {
                        $service = app(PayrollApprovalService::class);

                        try {
                            $service->submitForApproval($record, auth()->user());

                            Notification::make()
                                ->title('Submitted for Approval')
                                ->body('The payrun batch has been submitted for finance approval.')
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
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (PayrunBatch $record) => $record->status !== 'draft')
                    ->action(function (PayrunBatch $record) {
                        $filename = "payroll_{$record->period_end_date->format('Y_m')}.xlsx";

                        return Excel::download(new PayrollRecordsExport($record), $filename);
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PayrunBatch $record) => $record->isEditable()),
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
            RelationManagers\PayrollRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrunBatches::route('/'),
            'create' => Pages\CreatePayrunBatch::route('/create'),
            'view' => Pages\ViewPayrunBatch::route('/{record}'),
            'edit' => Pages\EditPayrunBatch::route('/{record}/edit'),
        ];
    }
}
