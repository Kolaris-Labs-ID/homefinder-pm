<?php

namespace App\Filament\Resources;

use App\Exports\PayrollRecordsExport;
use App\Filament\Resources\PayrunBatchResource\Pages;
use App\Filament\Resources\PayrunBatchResource\RelationManagers;
use App\Models\Hris\PayrunBatch;
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
                        'approved' => 'warning',
                        'paid' => 'success',
                    }),
                Tables\Columns\TextColumn::make('payroll_records_count')
                    ->label('Employees')
                    ->counts('payrollRecords'),
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
                        $results = $service->calculatePayrun($record);

                        Notification::make()
                            ->title('Payrun Calculated')
                            ->body("Processed: {$results['processed']}, Skipped: {$results['skipped']}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (PayrunBatch $record) => $record->canApprove())
                    ->requiresConfirmation()
                    ->action(function (PayrunBatch $record) {
                        $service = app(PayrollCalculationService::class);
                        $service->approve($record, auth()->user());

                        Notification::make()
                            ->title('Payrun Approved')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PayrunBatch $record) => $record->canMarkAsPaid())
                    ->requiresConfirmation()
                    ->action(function (PayrunBatch $record) {
                        $service = app(PayrollCalculationService::class);
                        $service->markAsPaid($record, auth()->user());

                        Notification::make()
                            ->title('Payrun Marked as Paid')
                            ->success()
                            ->send();
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
