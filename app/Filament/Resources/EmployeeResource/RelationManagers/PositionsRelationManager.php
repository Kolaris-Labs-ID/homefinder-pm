<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Hris\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('position_id')
                    ->relationship('position', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('manager_id')
                    ->relationship(
                        name: 'manager',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query->with('user')
                    )
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => $record->user->name ?? 'N/A')
                    ->searchable()
                    ->preload()
                    ->label('Manager'),
                Forms\Components\DatePicker::make('start_date')
                    ->required(),
                Forms\Components\DatePicker::make('end_date'),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary position')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('position.title')
                    ->label('Position')
                    ->badge(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge(),
                Tables\Columns\TextColumn::make('manager.user.name')
                    ->label('Manager')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('start_date')
                    ->date(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->placeholder('Present'),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('start_date', 'desc');
    }
}

