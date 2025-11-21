<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'contract' => 'Contract',
                        'ktp' => 'KTP',
                        'npwp' => 'NPWP',
                        'ijazah' => 'Ijazah',
                        'sp' => 'Warning Letter (SP)',
                        'other' => 'Other',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('file_path')
                    ->directory('hris/documents')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->required(),
                Forms\Components\DatePicker::make('issued_date'),
                Forms\Components\DatePicker::make('expiry_date'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->label('Type'),
                Tables\Columns\TextColumn::make('name')
                    ->limit(30),
                Tables\Columns\TextColumn::make('issued_date')
                    ->date(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->date()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => \Storage::url($record->file_path))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

