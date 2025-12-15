<?php

namespace App\Filament\Resources\PayrunBatchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PayrollRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrollRecords';

    protected static ?string $title = 'Payroll Records';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_income')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_bpjs_employee')
                    ->label('BPJS (Employee)')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('pph21_amount')
                    ->label('PPh 21')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('total_deductions')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('net_income')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }
}
