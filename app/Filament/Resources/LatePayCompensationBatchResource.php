<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LatePayCompensationBatchResource\Pages;
use App\Filament\Resources\LatePayCompensationBatchResource\RelationManagers;
use App\Models\Hris\LatePayCompensationBatch;
use App\Services\Hris\LatePayCompensationService;
use App\Services\Hris\PayrollApprovalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LatePayCompensationBatchResource extends Resource
{
    protected static ?string $model = LatePayCompensationBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Late Pay Compensation';

    protected static ?string $modelLabel = 'Late Pay Compensation Batch';

    protected static ?string $pluralModelLabel = 'Late Pay Compensation Batches';

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
                Forms\Components\Section::make('Pay dates')
                    ->description('Kompensasi keterlambatan gaji: 1% dari gaji pokok per hari kalender terlambat (transfer terpisah dari gaji utama).')
                    ->schema([
                        Forms\Components\DatePicker::make('scheduled_pay_date')
                            ->label('Scheduled pay date')
                            ->required(),
                        Forms\Components\DatePicker::make('actual_pay_date')
                            ->label('Actual salary payment date')
                            ->required(),
                        Forms\Components\DatePicker::make('compensation_transfer_date')
                            ->label('Compensation transfer date')
                            ->helperText('Optional — when the 1% compensation is paid separately.'),
                        Forms\Components\TextInput::make('rate_percent')
                            ->label('Rate (% per day)')
                            ->numeric()
                            ->default(1)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ])->columns(2),

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
                Tables\Columns\TextColumn::make('period_label')
                    ->label('Scheduled → Actual')
                    ->searchable(false),
                Tables\Columns\TextColumn::make('days_late')
                    ->label('Days late')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('rate_percent')
                    ->label('Rate %')
                    ->suffix('%')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('compensation_transfer_date')
                    ->label('Comp. transfer')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => LatePayCompensationBatch::getStatusColors()[$state] ?? 'gray')
                    ->description(fn (LatePayCompensationBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
                Tables\Columns\TextColumn::make('total_employees')
                    ->label('Employees')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('employees_with_warnings')
                    ->label('Warnings')
                    ->alignCenter()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LatePayCompensationBatch::getStatusLabels()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generateBatch')
                    ->label('Generate batch')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('scheduled_pay_date')
                            ->label('Scheduled pay date')
                            ->required(),
                        Forms\Components\DatePicker::make('actual_pay_date')
                            ->label('Actual salary payment date')
                            ->required(),
                        Forms\Components\DatePicker::make('compensation_transfer_date')
                            ->label('Compensation transfer date'),
                        Forms\Components\TextInput::make('rate_percent')
                            ->label('Rate (% per day)')
                            ->numeric()
                            ->default(1)
                            ->minValue(0)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $service = app(LatePayCompensationService::class);
                        try {
                            $transfer = ! empty($data['compensation_transfer_date'])
                                ? \Carbon\Carbon::parse($data['compensation_transfer_date'])
                                : null;
                            $batch = $service->generateBatch(
                                \Carbon\Carbon::parse($data['scheduled_pay_date']),
                                \Carbon\Carbon::parse($data['actual_pay_date']),
                                $transfer,
                                (float) $data['rate_percent'],
                            );
                            Notification::make()
                                ->title("Batch generated: {$batch->total_employees} employee(s), Rp ".number_format((float) $batch->total_amount, 0, ',', '.'))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Generation failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('submitForApproval')
                    ->label('Submit for approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (LatePayCompensationBatch $record) => $record->canSubmitForApproval())
                    ->requiresConfirmation()
                    ->action(function (LatePayCompensationBatch $record) {
                        try {
                            app(PayrollApprovalService::class)->submitForApproval($record, auth()->user());
                            Notification::make()->title('Submitted for approval')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LatePayCompensationBatch $record) => $record->isEditable()),
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLatePayCompensationBatches::route('/'),
            'create' => Pages\CreateLatePayCompensationBatch::route('/create'),
            'view' => Pages\ViewLatePayCompensationBatch::route('/{record}'),
            'edit' => Pages\EditLatePayCompensationBatch::route('/{record}/edit'),
        ];
    }
}
