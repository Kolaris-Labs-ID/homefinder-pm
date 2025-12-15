<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryStructureResource\Pages;
use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryStructureResource extends Resource
{
    protected static ?string $model = SalaryStructure::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(Employee::active()->with('user')->get()->pluck('user.name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\DatePicker::make('effective_date')
                            ->required()
                            ->default(now()),
                        Forms\Components\TextInput::make('basic_salary')
                            ->label('Basic Salary (IDR)')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(0),
                    ])->columns(3),

                Forms\Components\Section::make('PPh 21 & BPJS Settings')
                    ->schema([
                        Forms\Components\Select::make('pph21_method')
                            ->label('PPh 21 Method')
                            ->options([
                                'netto' => 'Netto (Pajak ditanggung karyawan)',
                                'gross' => 'Gross (Pajak ditanggung karyawan)',
                                'gross_up' => 'Gross Up (Pajak ditanggung perusahaan)',
                            ])
                            ->default('gross')
                            ->required(),
                        Forms\Components\Select::make('jkk_risk_level')
                            ->label('JKK Risk Level')
                            ->options([
                                'very_low' => 'Very Low (0.24%)',
                                'low' => 'Low (0.54%)',
                                'medium' => 'Medium (0.89%)',
                                'high' => 'High (1.27%)',
                                'very_high' => 'Very High (1.74%)',
                            ])
                            ->default('low')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Fixed Allowances')
                    ->schema([
                        Forms\Components\Repeater::make('allowances_fixed')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Fixed Allowance'),
                    ]),

                Forms\Components\Section::make('Variable Allowances')
                    ->schema([
                        Forms\Components\Repeater::make('allowances_variable')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Variable Allowance'),
                    ]),

                Forms\Components\Section::make('Deductions')
                    ->schema([
                        Forms\Components\Repeater::make('deductions')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('Rp'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add Deduction'),
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
                Tables\Columns\TextColumn::make('effective_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('basic_salary')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pph21_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'netto' => 'info',
                        'gross' => 'warning',
                        'gross_up' => 'success',
                    }),
                Tables\Columns\TextColumn::make('jkk_risk_level')
                    ->badge(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('pph21_method')
                    ->options([
                        'netto' => 'Netto',
                        'gross' => 'Gross',
                        'gross_up' => 'Gross Up',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalaryStructures::route('/'),
            'create' => Pages\CreateSalaryStructure::route('/create'),
            'edit' => Pages\EditSalaryStructure::route('/{record}/edit'),
        ];
    }
}
