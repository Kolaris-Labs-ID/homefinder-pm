<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceLogResource\Pages;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

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
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Employee & Attendance')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('employee.user.name')
                            ->label('Employee'),
                        Infolists\Components\TextEntry::make('date')
                            ->date(),
                        Infolists\Components\TextEntry::make('mode')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                AttendanceLog::MODE_OFFICE => 'primary',
                                AttendanceLog::MODE_WFH => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                AttendanceLog::MODE_OFFICE => 'Office',
                                AttendanceLog::MODE_WFH => 'Work From Home',
                                default => $state,
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                AttendanceLog::STATUS_PRESENT => 'success',
                                AttendanceLog::STATUS_LATE => 'warning',
                                AttendanceLog::STATUS_HALF_DAY => 'info',
                                AttendanceLog::STATUS_ABSENT => 'danger',
                                AttendanceLog::STATUS_LEAVE => 'gray',
                                AttendanceLog::STATUS_HOLIDAY => 'primary',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                AttendanceLog::STATUS_PRESENT => 'Present',
                                AttendanceLog::STATUS_LATE => 'Late',
                                AttendanceLog::STATUS_HALF_DAY => 'Half Day',
                                AttendanceLog::STATUS_ABSENT => 'Absent',
                                AttendanceLog::STATUS_LEAVE => 'Leave',
                                AttendanceLog::STATUS_HOLIDAY => 'Holiday',
                                default => $state ?? '-',
                            }),
                        Infolists\Components\TextEntry::make('source')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                AttendanceLog::SOURCE_WEB => 'primary',
                                AttendanceLog::SOURCE_MOBILE => 'success',
                                AttendanceLog::SOURCE_BIOMETRIC => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                AttendanceLog::SOURCE_WEB => 'Web',
                                AttendanceLog::SOURCE_MOBILE => 'Mobile',
                                AttendanceLog::SOURCE_BIOMETRIC => 'Biometric',
                                default => $state ?? '-',
                            }),
                    ]),

                Infolists\Components\Section::make('Check-In Details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('check_in_time')
                                ->label('Time')
                                ->dateTime('d M Y, H:i:s'),
                            Infolists\Components\TextEntry::make('check_in_address')
                                ->label('Address')
                                ->placeholder('No address recorded'),
                            Infolists\Components\TextEntry::make('check_in_coordinates')
                                ->label('Coordinates')
                                ->state(function (AttendanceLog $record): ?string {
                                    if ($record->check_in_latitude && $record->check_in_longitude) {
                                        return "{$record->check_in_latitude}, {$record->check_in_longitude}";
                                    }
                                    return null;
                                })
                                ->placeholder('No coordinates')
                                ->url(fn (AttendanceLog $record): ?string =>
                                    $record->check_in_latitude && $record->check_in_longitude
                                        ? "https://www.google.com/maps?q={$record->check_in_latitude},{$record->check_in_longitude}"
                                        : null
                                )
                                ->openUrlInNewTab(),
                        ]),
                        Infolists\Components\ImageEntry::make('check_in_photo_path')
                            ->label('Check-In Photo')
                            ->height(200)
                            ->extraImgAttributes(['class' => 'rounded-lg shadow-md'])
                            ->placeholder('No photo')
                            ->openUrlInNewTab()
                            ->url(fn (AttendanceLog $record): ?string => $record->check_in_photo_path)
                            ->formatStateUsing(function (?string $state): ?string {
                                if (!$state) return null;
                                if (str_starts_with($state, 'http')) return $state;
                                return Storage::disk('local')->url($state);
                            }),
                    ]),

                Infolists\Components\Section::make('Check-Out Details')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\Group::make([
                            Infolists\Components\TextEntry::make('check_out_time')
                                ->label('Time')
                                ->dateTime('d M Y, H:i:s')
                                ->placeholder('Not checked out yet'),
                            Infolists\Components\TextEntry::make('check_out_address')
                                ->label('Address')
                                ->placeholder('No address recorded'),
                            Infolists\Components\TextEntry::make('check_out_coordinates')
                                ->label('Coordinates')
                                ->state(function (AttendanceLog $record): ?string {
                                    if ($record->check_out_latitude && $record->check_out_longitude) {
                                        return "{$record->check_out_latitude}, {$record->check_out_longitude}";
                                    }
                                    return null;
                                })
                                ->placeholder('No coordinates')
                                ->url(fn (AttendanceLog $record): ?string =>
                                    $record->check_out_latitude && $record->check_out_longitude
                                        ? "https://www.google.com/maps?q={$record->check_out_latitude},{$record->check_out_longitude}"
                                        : null
                                )
                                ->openUrlInNewTab(),
                            Infolists\Components\TextEntry::make('working_hours')
                                ->label('Working Hours')
                                ->state(function (AttendanceLog $record): ?string {
                                    if (!$record->check_in_time || !$record->check_out_time) {
                                        return null;
                                    }
                                    $diff = $record->check_in_time->diff($record->check_out_time);
                                    return sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
                                })
                                ->placeholder('N/A'),
                        ]),
                        Infolists\Components\ImageEntry::make('check_out_photo_path')
                            ->label('Check-Out Photo')
                            ->height(200)
                            ->extraImgAttributes(['class' => 'rounded-lg shadow-md'])
                            ->placeholder('No photo')
                            ->openUrlInNewTab()
                            ->url(fn (AttendanceLog $record): ?string => $record->check_out_photo_path)
                            ->formatStateUsing(function (?string $state): ?string {
                                if (!$state) return null;
                                if (str_starts_with($state, 'http')) return $state;
                                return Storage::disk('local')->url($state);
                            }),
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
                Tables\Columns\ImageColumn::make('check_in_photo_path')
                    ->label('In Photo')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(fn () => null)
                    ->formatStateUsing(function (?string $state): ?string {
                        if (!$state) return null;
                        if (str_starts_with($state, 'http')) return $state;
                        return Storage::disk('local')->url($state);
                    }),
                Tables\Columns\TextColumn::make('check_out_time')
                    ->label('Check-Out')
                    ->dateTime('H:i')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('check_out_photo_path')
                    ->label('Out Photo')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(fn () => null)
                    ->formatStateUsing(function (?string $state): ?string {
                        if (!$state) return null;
                        if (str_starts_with($state, 'http')) return $state;
                        return Storage::disk('local')->url($state);
                    }),
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
                Tables\Filters\TernaryFilter::make('has_photos')
                    ->label('Has Photos')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNotNull('check_in_photo_path')
                              ->orWhereNotNull('check_out_photo_path');
                        }),
                        false: fn (Builder $query) => $query->whereNull('check_in_photo_path')
                            ->whereNull('check_out_photo_path'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('check_in_time', 'desc');
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
        return (string) static::getModel()::whereDate('date', now('Asia/Jakarta')->toDateString())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
