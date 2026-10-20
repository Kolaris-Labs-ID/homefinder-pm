<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HiringCompanyResource\Pages;
use App\Models\Hris\HiringCompany;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HiringCompanyResource extends Resource
{
    protected static ?string $model = HiringCompany::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Hiring Companies';

    protected static ?string $navigationGroup = 'Recruitment';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255)
                            ->helperText('Auto-generated from the name if left blank. Locked once a public job is published.')
                            ->disabled(fn (?HiringCompany $record): bool => (bool) $record?->hasPublishedPublicJob())
                            ->dehydrated(),
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('industry')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('hiring-companies/logos')
                            ->visibility('public')
                            ->maxSize(1024),
                    ]),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_internal')
                            ->label('Internal company (Homefinder)'),
                        Forms\Components\Select::make('status')
                            ->options([
                                HiringCompany::STATUS_ACTIVE => 'Active',
                                HiringCompany::STATUS_INACTIVE => 'Inactive',
                            ])
                            ->default(HiringCompany::STATUS_ACTIVE)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('industry')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_internal')
                    ->boolean()
                    ->label('Internal'),
                Tables\Columns\TextColumn::make('job_postings_count')
                    ->counts('jobPostings')
                    ->label('Jobs'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => HiringCompany::STATUS_ACTIVE,
                        'gray' => HiringCompany::STATUS_INACTIVE,
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        HiringCompany::STATUS_ACTIVE => 'Active',
                        HiringCompany::STATUS_INACTIVE => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHiringCompanies::route('/'),
            'create' => Pages\CreateHiringCompany::route('/create'),
            'view' => Pages\ViewHiringCompany::route('/{record}'),
            'edit' => Pages\EditHiringCompany::route('/{record}/edit'),
        ];
    }
}
