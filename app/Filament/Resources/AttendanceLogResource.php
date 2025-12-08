<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceLogResource\Pages;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    protected static ?string $navigationGroup = 'HRIS';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Attendance Logs';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Attendance Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee.user', 'name')
                            ->label('Employee')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('mode')
                            ->options([
                                AttendanceLog::MODE_OFFICE => 'Office',
                                AttendanceLog::MODE_WFH => 'Work From Home',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('source')
                            ->options([
                                AttendanceLog::SOURCE_WEB => 'Web',
                                AttendanceLog::SOURCE_MOBILE => 'Mobile',
                                AttendanceLog::SOURCE_BIOMETRIC => 'Biometric',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                AttendanceLog::STATUS_PRESENT => 'Present',
                                AttendanceLog::STATUS_LATE => 'Late',
                                AttendanceLog::STATUS_HALF_DAY => 'Half Day',
                                AttendanceLog::STATUS_ABSENT => 'Absent',
                                AttendanceLog::STATUS_LEAVE => 'Leave',
                                AttendanceLog::STATUS_HOLIDAY => 'Holiday',
                            ])
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Check-In Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('check_in_time')
                            ->label('Check-In Time')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_in_address')
                            ->label('Check-In Address')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_in_latitude')
                            ->label('Latitude')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_in_longitude')
                            ->label('Longitude')
                            ->disabled(),
                        Forms\Components\FileUpload::make('check_in_photo_path')
                            ->label('Check-In Photo')
                            ->image()
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Check-Out Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('check_out_time')
                            ->label('Check-Out Time')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_out_address')
                            ->label('Check-Out Address')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_out_latitude')
                            ->label('Latitude')
                            ->disabled(),
                        Forms\Components\TextInput::make('check_out_longitude')
                            ->label('Longitude')
                            ->disabled(),
                        Forms\Components\FileUpload::make('check_out_photo_path')
                            ->label('Check-Out Photo')
                            ->image()
                            ->disabled()
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
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('mode')
                    ->colors([
                        'primary' => AttendanceLog::MODE_OFFICE,
                        'success' => AttendanceLog::MODE_WFH,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AttendanceLog::MODE_OFFICE => 'Office',
                        AttendanceLog::MODE_WFH => 'WFH',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('check_in_time')
                    ->label('Check-In')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('Check-Out')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => AttendanceLog::STATUS_PRESENT,
                        'warning' => AttendanceLog::STATUS_LATE,
                        'info' => AttendanceLog::STATUS_HALF_DAY,
                        'danger' => AttendanceLog::STATUS_ABSENT,
                        'gray' => AttendanceLog::STATUS_LEAVE,
                        'primary' => AttendanceLog::STATUS_HOLIDAY,
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        AttendanceLog::STATUS_PRESENT => 'Present',
                        AttendanceLog::STATUS_LATE => 'Late',
                        AttendanceLog::STATUS_HALF_DAY => 'Half Day',
                        AttendanceLog::STATUS_ABSENT => 'Absent',
                        AttendanceLog::STATUS_LEAVE => 'Leave',
                        AttendanceLog::STATUS_HOLIDAY => 'Holiday',
                        default => $state ?? '-',
                    }),
                Tables\Columns\BadgeColumn::make('source')
                    ->colors([
                        'primary' => AttendanceLog::SOURCE_WEB,
                        'success' => AttendanceLog::SOURCE_MOBILE,
                        'warning' => AttendanceLog::SOURCE_BIOMETRIC,
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        AttendanceLog::SOURCE_WEB => 'Web',
                        AttendanceLog::SOURCE_MOBILE => 'Mobile',
                        AttendanceLog::SOURCE_BIOMETRIC => 'Biometric',
                        default => $state ?? '-',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('check_in_address')
                    ->label('Location')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee.user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        AttendanceLog::STATUS_PRESENT => 'Present',
                        AttendanceLog::STATUS_LATE => 'Late',
                        AttendanceLog::STATUS_HALF_DAY => 'Half Day',
                        AttendanceLog::STATUS_ABSENT => 'Absent',
                        AttendanceLog::STATUS_LEAVE => 'Leave',
                        AttendanceLog::STATUS_HOLIDAY => 'Holiday',
                    ]),
                Tables\Filters\SelectFilter::make('mode')
                    ->options([
                        AttendanceLog::MODE_OFFICE => 'Office',
                        AttendanceLog::MODE_WFH => 'Work From Home',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceLogs::route('/'),
            'view' => Pages\ViewAttendanceLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::whereDate('date', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
