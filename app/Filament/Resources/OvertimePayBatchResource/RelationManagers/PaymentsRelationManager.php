<?php

namespace App\Filament\Resources\OvertimePayBatchResource\RelationManagers;

use App\Models\Hris\OvertimePayment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Employee Overtime Payments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('total_sessions')
                            ->label('Sessions')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('total_duration_minutes')
                            ->label('Total Minutes')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Bank Information')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')->label('Bank Name'),
                        Forms\Components\TextInput::make('bank_account_number')->label('Account Number'),
                        Forms\Components\TextInput::make('bank_account_holder')->label('Account Holder'),
                    ])->columns(3),

                Forms\Components\Section::make('Notes')
                    ->schema([
                        Forms\Components\Textarea::make('hr_notes')->label('HR Notes')->rows(2),
                        Forms\Components\Textarea::make('finance_notes')->label('Finance Notes')->rows(2),
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
                Tables\Columns\TextColumn::make('total_sessions')
                    ->label('Sessions')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_duration_minutes')
                    ->label('Minutes')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state): string => number_format($state / 60, 1) . 'h'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('IDR')
                    ->sortable(),
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
                        'pending'  => 'gray',
                        'approved' => 'success',
                        'excluded' => 'danger',
                        'paid'     => 'info',
                        default    => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_bank_warning')
                    ->label('Bank Warning')
                    ->placeholder('All')
                    ->trueLabel('With Warnings')
                    ->falseLabel('No Warnings'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(OvertimePayment::getStatusLabels()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('refresh_bank_info')
                    ->label('Refresh Bank Info')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn () => $this->getOwnerRecord()->isEditable())
                    ->requiresConfirmation()
                    ->action(function () {
                        $batch   = $this->getOwnerRecord();
                        $updated = 0;

                        foreach ($batch->payments()->where('has_bank_warning', true)->get() as $payment) {
                            $payment->refreshBankInfo();
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
                Tables\Actions\Action::make('exclude')
                    ->label('Exclude')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (OvertimePayment $record) =>
                        $record->status === 'pending' && $this->getOwnerRecord()->isEditable())
                    ->requiresConfirmation()
                    ->action(function (OvertimePayment $record) {
                        $record->update(['status' => 'excluded']);
                        $this->getOwnerRecord()->recalculateSummary();
                        Notification::make()->title('Payment Excluded')->success()->send();
                    }),
                Tables\Actions\Action::make('include')
                    ->label('Include')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->visible(fn (OvertimePayment $record) =>
                        $record->status === 'excluded' && $this->getOwnerRecord()->isEditable())
                    ->action(function (OvertimePayment $record) {
                        $record->update(['status' => 'pending']);
                        $this->getOwnerRecord()->recalculateSummary();
                        Notification::make()->title('Payment Included')->success()->send();
                    }),
                Tables\Actions\Action::make('view_sessions')
                    ->label('Sessions')
                    ->icon('heroicon-o-list-bullet')
                    ->color('gray')
                    ->modalContent(fn (OvertimePayment $record) => view(
                        'filament.modals.overtime-sessions',
                        ['payment' => $record]
                    ))
                    ->modalSubmitAction(false),
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
                        ->label('Exclude Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn () => $this->getOwnerRecord()->isEditable())
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(fn ($r) => $r->update(['status' => 'excluded']));
                            $this->getOwnerRecord()->recalculateSummary();
                            Notification::make()->title('Selected payments excluded')->success()->send();
                        }),
                ]),
            ]);
    }
}
