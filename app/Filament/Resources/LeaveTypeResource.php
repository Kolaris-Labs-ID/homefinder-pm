<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveTypeResource\Pages;
use App\Models\Hris\LeaveType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveTypeResource extends Resource
{
    protected static ?string $model = LeaveType::class;

    protected static ?string $navigationGroup = 'HRIS';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('default_annual_quota')
                ->numeric()
                ->label('Default Quota'),
            Forms\Components\Toggle::make('requires_approval')
                ->default(true),
            Forms\Components\Toggle::make('carry_forward_allowed'),
            Forms\Components\Toggle::make('is_special'),
            Forms\Components\Select::make('gender_constraint')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                ])
                ->placeholder('None'),
            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('default_annual_quota')
                    ->label('Quota')
                    ->sortable(),
                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Approval?')
                    ->boolean(),
                Tables\Columns\IconColumn::make('carry_forward_allowed')
                    ->label('Carry Forward')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveTypes::route('/'),
            'create' => Pages\CreateLeaveType::route('/create'),
            'edit' => Pages\EditLeaveType::route('/{record}/edit'),
        ];
    }
}

