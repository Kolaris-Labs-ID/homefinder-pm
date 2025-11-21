<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('movement_type')
                    ->options([
                        'promotion' => 'Promotion',
                        'demotion' => 'Demotion',
                        'transfer' => 'Transfer',
                        'salary_increase' => 'Salary Increase',
                        'salary_decrease' => 'Salary Decrease',
                    ])
                    ->required(),
                Forms\Components\Select::make('from_department_id')
                    ->relationship('fromDepartment', 'name')
                    ->label('From Department')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('to_department_id')
                    ->relationship('toDepartment', 'name')
                    ->label('To Department')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('from_position_id')
                    ->relationship('fromPosition', 'title')
                    ->label('From Position')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('to_position_id')
                    ->relationship('toPosition', 'title')
                    ->label('To Position')
                    ->searchable()
                    ->preload(),
                Forms\Components\DatePicker::make('effective_date')
                    ->required(),
                Forms\Components\TextInput::make('from_salary')
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\TextInput::make('to_salary')
                    ->numeric()
                    ->prefix('Rp'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('movement_type')
                    ->badge()
                    ->label('Type'),
                Tables\Columns\TextColumn::make('effective_date')
                    ->date(),
                Tables\Columns\TextColumn::make('fromDepartment.name')
                    ->label('From Dept')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('toDepartment.name')
                    ->label('To Dept')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('toPosition.title')
                    ->label('New Position')
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('effective_date', 'desc');
    }
}

