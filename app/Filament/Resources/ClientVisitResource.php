<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientVisitResource\Pages;
use App\Models\Hris\ClientVisit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientVisitResource extends Resource
{
    protected static ?string $model = ClientVisit::class;

    protected static ?string $navigationGroup = 'HRIS';

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Client Visits';

    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Visit Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee.user', 'name')
                            ->label('Employee')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DatePicker::make('visit_date')
                            ->label('Visit Date')
                            ->required()
                            ->minDate(today())
                            ->rule('after_or_equal:today'),
                        Forms\Components\TextInput::make('client_name')
                            ->label('Client Name')
                            ->required(),
                        Forms\Components\TextInput::make('client_address')
                            ->label('Client Address')
                            ->required(),
                        Forms\Components\Select::make('purpose')
                            ->options(ClientVisit::getPurposeLabels())
                            ->required(),
                        Forms\Components\TextInput::make('purpose_detail')
                            ->label('Purpose Detail'),
                        Forms\Components\TimePicker::make('planned_start_time')
                            ->label('Planned Start Time'),
                        Forms\Components\TimePicker::make('planned_end_time')
                            ->label('Planned End Time'),
                        Forms\Components\Select::make('status')
                            ->options(ClientVisit::getStatusLabels())
                            ->default(ClientVisit::STATUS_PLANNED)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Visit Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('visit_type')
                    ->label('Visit Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ClientVisit::TYPE_SUDDEN => 'danger',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => ClientVisit::getVisitTypeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('purpose')
                    ->formatStateUsing(fn (string $state): string => ClientVisit::getPurposeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ClientVisit::STATUS_IN_PROGRESS => 'warning',
                        ClientVisit::STATUS_COMPLETED => 'success',
                        ClientVisit::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ClientVisit::getStatusLabels()[$state] ?? $state),
            ])
            ->defaultSort('visit_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientVisits::route('/'),
            'create' => Pages\CreateClientVisit::route('/create'),
            'edit' => Pages\EditClientVisit::route('/{record}/edit'),
        ];
    }

    public static function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Visit Details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('employee_id'),
                    Forms\Components\DatePicker::make('visit_date'),
                    Forms\Components\TextInput::make('client_name'),
                    Forms\Components\TextInput::make('client_address'),
                    Forms\Components\Select::make('purpose'),
                    Forms\Components\TextInput::make('purpose_detail'),
                    Forms\Components\TimePicker::make('planned_start_time'),
                    Forms\Components\TimePicker::make('planned_end_time'),
                    Forms\Components\Select::make('status'),
                    Forms\Components\Textarea::make('notes'),
                ]),
        ];
    }

    public static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('employee.user.name'),
            Tables\Columns\TextColumn::make('client_name'),
            Tables\Columns\TextColumn::make('visit_date'),
            Tables\Columns\TextColumn::make('visit_type'),
            Tables\Columns\TextColumn::make('purpose'),
            Tables\Columns\TextColumn::make('status'),
        ];
    }
}
