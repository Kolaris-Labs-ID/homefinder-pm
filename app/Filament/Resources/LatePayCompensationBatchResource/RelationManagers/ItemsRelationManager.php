<?php

namespace App\Filament\Resources\LatePayCompensationBatchResource\RelationManagers;

use App\Models\Hris\LatePayCompensationItem;
use App\Models\Hris\LatePayCompensationPartialPayment;
use App\Services\Hris\LatePayCompensationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Employee compensation';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Calculation')
                    ->schema([
                        Forms\Components\TextInput::make('basic_salary_snapshot')
                            ->label('Basic salary (snapshot)')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled(),
                        Forms\Components\TextInput::make('days_late')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('rate_percent')
                            ->label('Rate %')
                            ->numeric()
                            ->suffix('%')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Bank information')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->label('Bank name'),
                        Forms\Components\TextInput::make('bank_account_number')->label('Account number'),
                        Forms\Components\TextInput::make('bank_account_holder')->label('Account holder'),
                    ])->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('hr_notes')->label('HR notes')->rows(2),
                        Forms\Components\Textarea::make('finance_notes')->label('Finance notes')->rows(2),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('basic_salary_snapshot')
                    ->label('Basic salary')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('days_late')
                    ->label('Days')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('rate_percent')
                    ->label('Rate %')
                    ->suffix('%')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('partial_payments_count')
                    ->label('Partials')
                    ->counts('partialPayments')
                    ->alignCenter()
                    ->default(0),
                Tables\Columns\TextColumn::make('paid_percent_display')
                    ->label('Paid %')
                    ->alignCenter()
                    ->state(fn (LatePayCompensationItem $record): string => number_format($record->totalPaidPercent(), 2).'%'),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label('Bank')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bank_account_number')
                    ->label('Account')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('has_bank_warning')
                    ->label('Warning')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'excluded' => 'danger',
                        'paid' => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_bank_warning')
                    ->label('Bank warning')
                    ->placeholder('All')
                    ->trueLabel('With warnings')
                    ->falseLabel('No warnings'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(LatePayCompensationItem::getStatusLabels()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('refresh_bank_info')
                    ->label('Refresh bank info')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn () => $this->getOwnerRecord()->isEditable())
                    ->requiresConfirmation()
                    ->action(function () {
                        $batch = $this->getOwnerRecord();
                        $updated = 0;

                        foreach ($batch->items()->where('has_bank_warning', true)->get() as $item) {
                            $item->refreshBankInfo();
                            $updated++;
                        }

                        $batch->recalculateSummary();

                        Notification::make()
                            ->title("Refreshed {$updated} record(s)")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('add_partial_payment')
                    ->label('Add partial payment')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn (LatePayCompensationItem $record) => $record->isEditable())
                    ->form(fn (LatePayCompensationItem $record) => [
                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Payment date')
                            ->minDate($this->getOwnerRecord()->scheduled_pay_date)
                            ->maxDate($this->getOwnerRecord()->actual_pay_date)
                            ->required(),
                        Forms\Components\TextInput::make('paid_percent')
                            ->label('Paid %')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100 - $record->totalPaidPercent())
                            ->suffix('%')
                            ->required()
                            ->helperText('Remaining available: '.number_format(100 - $record->totalPaidPercent(), 2).'%'),
                    ])
                    ->action(function (LatePayCompensationItem $record, array $data) {
                        try {
                            LatePayCompensationPartialPayment::create([
                                'late_pay_compensation_item_id' => $record->id,
                                'payment_date' => $data['payment_date'],
                                'paid_percent' => $data['paid_percent'],
                            ]);

                            app(LatePayCompensationService::class)->recalculateItem($record);
                            $this->getOwnerRecord()->recalculateSummary();

                            Notification::make()
                                ->title('Partial payment added & amount recalculated')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('view_partial_payments')
                    ->label('View partials')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->visible(fn (LatePayCompensationItem $record) => $record->partialPayments()->count() > 0)
                    ->modalHeading('Partial payments')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(fn (LatePayCompensationItem $record) => [
                        \Filament\Infolists\Components\RepeatableEntry::make('partialPayments')
                            ->label('')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('payment_date')
                                    ->label('Date')
                                    ->date(),
                                \Filament\Infolists\Components\TextEntry::make('paid_percent')
                                    ->label('Paid %')
                                    ->suffix('%'),
                            ])
                            ->columns(2),
                    ]),
                Tables\Actions\Action::make('delete_partial_payment')
                    ->label('Remove partial')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (LatePayCompensationItem $record) => $record->isEditable() && $record->partialPayments()->count() > 0)
                    ->form(fn (LatePayCompensationItem $record) => [
                        Forms\Components\Select::make('partial_payment_id')
                            ->label('Select partial payment to remove')
                            ->options(
                                $record->partialPayments()
                                    ->orderBy('payment_date')
                                    ->get()
                                    ->mapWithKeys(fn ($p) => [
                                        $p->id => $p->payment_date->format('d M Y').' — '.$p->paid_percent.'%',
                                    ])
                            )
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (LatePayCompensationItem $record, array $data) {
                        try {
                            LatePayCompensationPartialPayment::where('id', $data['partial_payment_id'])
                                ->where('late_pay_compensation_item_id', $record->id)
                                ->delete();

                            app(LatePayCompensationService::class)->recalculateItem($record);
                            $this->getOwnerRecord()->recalculateSummary();

                            Notification::make()
                                ->title('Partial payment removed & amount recalculated')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('exclude')
                    ->label('Exclude')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (LatePayCompensationItem $record) => $record->status === 'pending' && $this->getOwnerRecord()->isEditable())
                    ->requiresConfirmation()
                    ->action(function (LatePayCompensationItem $record) {
                        $record->update(['status' => 'excluded']);
                        $this->getOwnerRecord()->recalculateSummary();
                        Notification::make()->title('Excluded')->success()->send();
                    }),
                Tables\Actions\Action::make('include')
                    ->label('Include')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn (LatePayCompensationItem $record) => $record->status === 'excluded' && $this->getOwnerRecord()->isEditable())
                    ->action(function (LatePayCompensationItem $record) {
                        $record->update(['status' => 'pending']);
                        $this->getOwnerRecord()->recalculateSummary();
                        Notification::make()->title('Included')->success()->send();
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isEditable())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['has_bank_warning'] = empty($data['bank_name'])
                            || empty($data['bank_account_number'])
                            || empty($data['bank_account_holder']);

                        return $data;
                    })
                    ->after(function () {
                        $this->getOwnerRecord()->recalculateSummary();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('exclude_selected')
                        ->label('Exclude selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn () => $this->getOwnerRecord()->isEditable())
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['status' => 'excluded']));
                            $this->getOwnerRecord()->recalculateSummary();
                            Notification::make()->title('Selected items excluded')->success()->send();
                        }),
                ]),
            ]);
    }
}
