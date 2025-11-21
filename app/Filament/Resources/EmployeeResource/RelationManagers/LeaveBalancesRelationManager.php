<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveBalancesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaveBalances';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('leave_type_id')
                    ->relationship('leaveType', 'name')
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\TextInput::make('year')
                    ->numeric()
                    ->required()
                    ->default(now()->year),
                Forms\Components\TextInput::make('starting_balance')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('used_days')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('remaining_days')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('Leave Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('year')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starting_balance')
                    ->label('Starting')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_days')
                    ->label('Used')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_days')
                    ->label('Remaining')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state <= 0 ? 'danger' : null),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

