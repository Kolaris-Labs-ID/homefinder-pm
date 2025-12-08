<?php

namespace App\Filament\Resources\AttendanceLogResource\Pages;

use App\Filament\Resources\AttendanceLogResource;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceLog extends ViewRecord
{
    protected static string $resource = AttendanceLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Attendance Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('employee.user.name')
                            ->label('Employee'),
                        TextEntry::make('date')
                            ->date(),
                        TextEntry::make('mode')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'office' => 'primary',
                                'wfh' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'office' => 'Office',
                                'wfh' => 'Work From Home',
                                default => $state,
                            }),
                        TextEntry::make('source')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'web' => 'primary',
                                'mobile' => 'success',
                                'biometric' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'web' => 'Web',
                                'mobile' => 'Mobile',
                                'biometric' => 'Biometric',
                                default => $state ?? '-',
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'present' => 'success',
                                'late' => 'warning',
                                'half_day' => 'info',
                                'absent' => 'danger',
                                'leave' => 'gray',
                                'holiday' => 'primary',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'present' => 'Present',
                                'late' => 'Late',
                                'half_day' => 'Half Day',
                                'absent' => 'Absent',
                                'leave' => 'Leave',
                                'holiday' => 'Holiday',
                                default => $state ?? '-',
                            }),
                    ]),

                Section::make('Check-In Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('check_in_time')
                            ->label('Check-In Time')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('check_in_address')
                            ->label('Address')
                            ->placeholder('No address recorded'),
                        TextEntry::make('check_in_latitude')
                            ->label('Latitude')
                            ->placeholder('-'),
                        TextEntry::make('check_in_longitude')
                            ->label('Longitude')
                            ->placeholder('-'),
                        ImageEntry::make('check_in_photo_path')
                            ->label('Check-In Photo')
                            ->disk('public')
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->check_in_photo_path)),
                    ]),

                Section::make('Check-Out Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('check_out_time')
                            ->label('Check-Out Time')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('Not checked out'),
                        TextEntry::make('check_out_address')
                            ->label('Address')
                            ->placeholder('No address recorded'),
                        TextEntry::make('check_out_latitude')
                            ->label('Latitude')
                            ->placeholder('-'),
                        TextEntry::make('check_out_longitude')
                            ->label('Longitude')
                            ->placeholder('-'),
                        ImageEntry::make('check_out_photo_path')
                            ->label('Check-Out Photo')
                            ->disk('public')
                            ->columnSpanFull()
                            ->visible(fn ($record) => filled($record->check_out_photo_path)),
                    ]),

                Section::make('GPS Coordinates Map')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('check_in_coordinates')
                                    ->label('Check-In Location')
                                    ->state(fn ($record) => $record->check_in_latitude && $record->check_in_longitude
                                        ? "{$record->check_in_latitude}, {$record->check_in_longitude}"
                                        : null)
                                    ->placeholder('No GPS data')
                                    ->url(fn ($record) => $record->check_in_latitude && $record->check_in_longitude
                                        ? "https://www.google.com/maps?q={$record->check_in_latitude},{$record->check_in_longitude}"
                                        : null)
                                    ->openUrlInNewTab(),
                                TextEntry::make('check_out_coordinates')
                                    ->label('Check-Out Location')
                                    ->state(fn ($record) => $record->check_out_latitude && $record->check_out_longitude
                                        ? "{$record->check_out_latitude}, {$record->check_out_longitude}"
                                        : null)
                                    ->placeholder('No GPS data')
                                    ->url(fn ($record) => $record->check_out_latitude && $record->check_out_longitude
                                        ? "https://www.google.com/maps?q={$record->check_out_latitude},{$record->check_out_longitude}"
                                        : null)
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->check_in_latitude || $record->check_out_latitude),
            ]);
    }
}
