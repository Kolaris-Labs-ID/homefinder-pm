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
                            ->helperText('Pajak Penghasilan Pasal 21 - Pajak atas penghasilan yang diterima karyawan')
                            ->options([
                                'netto' => 'Netto (Pajak ditanggung karyawan)',
                                'gross' => 'Gross (Pajak ditanggung karyawan)',
                                'gross_up' => 'Gross Up (Pajak ditanggung perusahaan)',
                            ])
                            ->default('gross')
                            ->required(),
                        Forms\Components\Toggle::make('bpjs_enabled')
                            ->label('Enable BPJS')
                            ->helperText('Aktifkan perhitungan iuran BPJS untuk karyawan ini')
                            ->default(true)
                            ->reactive()
                            ->columnSpanFull(),
                        Forms\Components\Select::make('jkk_risk_level')
                            ->label('JKK Risk Level')
                            ->helperText('Jaminan Kecelakaan Kerja - Program BPJS untuk perlindungan risiko kecelakaan saat bekerja')
                            ->options([
                                'very_low' => 'Very Low (0.24%)',
                                'low' => 'Low (0.54%)',
                                'medium' => 'Medium (0.89%)',
                                'high' => 'High (1.27%)',
                                'very_high' => 'Very High (1.74%)',
                            ])
                            ->default('low')
                            ->required(fn ($get) => $get('bpjs_enabled'))
                            ->visible(fn ($get) => $get('bpjs_enabled')),
                        Forms\Components\Select::make('bpjs_paid_by')
                            ->label('BPJS Paid By')
                            ->options(SalaryStructure::getBpjsPaidByLabels())
                            ->default(SalaryStructure::BPJS_PAID_BY_EMPLOYEE)
                            ->required(fn ($get) => $get('bpjs_enabled'))
                            ->visible(fn ($get) => $get('bpjs_enabled'))
                            ->helperText('Who pays the employee portion of BPJS contributions'),
                    ])->columns(3),

                Forms\Components\Section::make('Prorate & Daily Allowance Settings')
                    ->description('Configure how salary is calculated based on attendance')
                    ->schema([
                        Forms\Components\Toggle::make('prorate_enabled')
                            ->label('Enable Prorate Calculation')
                            ->helperText('Calculate salary based on actual days worked vs total working days')
                            ->default(true),
                        Forms\Components\TextInput::make('daily_allowance_rate')
                            ->label('Daily Allowance Rate (Uang Harian)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->helperText('Amount paid per day of attendance (leave empty if not applicable)'),
                        Forms\Components\Toggle::make('weekly_allowance_enabled')
                            ->label('Enable Weekly Allowance Disbursement')
                            ->helperText('Pay daily allowance weekly instead of monthly. Requires daily_allowance_rate to be set.')
                            ->default(false)
                            ->visible(fn ($get) => $get('daily_allowance_rate') > 0),
                    ])->columns(3),

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
                    ->description('Configure allowances that may vary based on attendance')
                    ->schema([
                        Forms\Components\Repeater::make('allowances_variable')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'fixed' => 'Fixed Amount',
                                        'attendance_based' => 'Attendance Based (per day)',
                                    ])
                                    ->default('fixed')
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Fixed Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->visible(fn ($get) => $get('type') === 'fixed')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('per_day_amount')
                                    ->label('Per Day Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->visible(fn ($get) => $get('type') === 'attendance_based')
                                    ->columnSpan(1),
                                Forms\Components\Toggle::make('count_late')
                                    ->label('Count Late Days')
                                    ->default(true)
                                    ->visible(fn ($get) => $get('type') === 'attendance_based')
                                    ->columnSpan(1),
                                Forms\Components\Toggle::make('count_wfh')
                                    ->label('Count WFH Days')
                                    ->default(true)
                                    ->visible(fn ($get) => $get('type') === 'attendance_based')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('max_amount')
                                    ->label('Max Amount (optional)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->visible(fn ($get) => $get('type') === 'attendance_based')
                                    ->helperText('Leave empty for no limit')
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add Variable Allowance')
                            ->collapsible()
                            ->cloneable(),
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
                Tables\Columns\TextColumn::make('daily_allowance_rate')
                    ->label('Daily Allowance')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('prorate_enabled')
                    ->label('Prorate')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('weekly_allowance_enabled')
                    ->label('Weekly')
                    ->boolean()
                    ->trueIcon('heroicon-o-calendar-days')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bpjs_paid_by')
                    ->label('BPJS By')
                    ->tooltip('Badan Penyelenggara Jaminan Sosial - Program jaminan sosial wajib bagi pekerja di Indonesia')
                    ->badge()
                    ->color(fn (?string $state, $record): string => match (true) {
                        !$record->bpjs_enabled => 'danger',
                        $state === 'company' => 'success',
                        $state === 'shared' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state, $record): string => !$record->bpjs_enabled
                        ? 'Disabled'
                        : match ($state) {
                            'employee' => 'Employee',
                            'company' => 'Company',
                            'shared' => 'Shared',
                            default => 'Employee',
                        })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pph21_method')
                    ->label('PPh 21')
                    ->tooltip('Pajak Penghasilan Pasal 21 - Pajak atas penghasilan yang diterima karyawan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'netto' => 'info',
                        'gross' => 'warning',
                        'gross_up' => 'success',
                    }),
                Tables\Columns\TextColumn::make('jkk_risk_level')
                    ->label('JKK')
                    ->tooltip('Jaminan Kecelakaan Kerja - Program BPJS untuk perlindungan risiko kecelakaan saat bekerja')
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
