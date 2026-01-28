<?php

namespace App\Filament\Resources;

use App\Exports\HeadcountExport;
use App\Exports\TurnoverExport;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Employees';

    protected static ?string $navigationGroup = 'HRIS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identity & Employment')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?Employee $record) => filled($record)),
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK')
                            ->maxLength(32),
                        Forms\Components\DatePicker::make('hire_date')
                            ->label('Hire Date')
                            ->required(),
                        Forms\Components\Select::make('employment_status')
                            ->options([
                                'permanent' => 'Permanent',
                                'probation' => 'Probation',
                                'contract' => 'Contract',
                                'intern' => 'Intern',
                                'freelance' => 'Freelance',
                            ])
                            ->required()
                            ->default('permanent'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\DatePicker::make('employment_end_date')
                            ->label('Employment End Date'),
                    ]),

                Forms\Components\Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('place_of_birth')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date_of_birth'),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ])
                            ->searchable(),
                        Forms\Components\Select::make('marital_status')
                            ->options([
                                'single' => 'Single',
                                'married' => 'Married',
                                'divorced' => 'Divorced',
                                'widowed' => 'Widowed',
                            ])
                            ->searchable(),
                        Forms\Components\Textarea::make('address')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('phone')
                            ->tel(),
                        Forms\Components\TextInput::make('emergency_contact_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('emergency_contact_phone')
                            ->tel(),
                    ]),

                Forms\Components\Section::make('Financial & Government')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_holder')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bank_account_number')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('npwp_number')
                            ->label('NPWP')
                            ->tooltip('Nomor Pokok Wajib Pajak - Nomor identitas wajib pajak untuk pelaporan pajak')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bpjs_kesehatan_number')
                            ->label('BPJS Kesehatan')
                            ->tooltip('Badan Penyelenggara Jaminan Sosial Kesehatan - Nomor kepesertaan jaminan kesehatan')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bpjs_ketenagakerjaan_number')
                            ->label('BPJS Ketenagakerjaan')
                            ->tooltip('Badan Penyelenggara Jaminan Sosial Ketenagakerjaan - Nomor kepesertaan jaminan ketenagakerjaan (JHT, JKK, JKM, JP)')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryPosition.position.title')
                    ->label('Position')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('primaryPosition.department.name')
                    ->label('Department')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'permanent',
                        'primary' => 'probation',
                        'warning' => fn ($state) => in_array($state, ['contract', 'freelance']),
                        'info' => 'intern',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employment_status')
                    ->options([
                        'permanent' => 'Permanent',
                        'probation' => 'Probation',
                        'contract' => 'Contract',
                        'intern' => 'Intern',
                        'freelance' => 'Freelance',
                    ]),
                Tables\Filters\Filter::make('active_only')
                    ->label('Active Employees')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->toggle(),
                Tables\Filters\Filter::make('has_position')
                    ->label('Assigned Position')
                    ->query(fn (Builder $query): Builder => $query->whereHas('primaryPosition')),
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
            ->headerActions([
                Tables\Actions\Action::make('exportHeadcount')
                    ->label('Export Headcount')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(
                            new HeadcountExport(),
                            'headcount-report-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
                Tables\Actions\Action::make('exportTurnover')
                    ->label('Export Turnover')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfYear())
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $start = Carbon::parse($data['start_date']);
                        $end = Carbon::parse($data['end_date']);
                        return Excel::download(
                            new TurnoverExport($start, $end),
                            'turnover-report-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
            ])
            ->defaultSort('hire_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PositionsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\MovementsRelationManager::class,
            RelationManagers\LeaveBalancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'user.name',
            'user.email',
            'nik',
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}

