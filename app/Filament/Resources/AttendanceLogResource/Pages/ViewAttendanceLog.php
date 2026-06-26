<?php

namespace App\Filament\Resources\AttendanceLogResource\Pages;

use App\Filament\Resources\AttendanceLogResource;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use Filament\Infolists\Components\Group;
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
                            ->color(fn (?string $state): string => match ($state) {
                                'office' => 'primary',
                                'wfh' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'office' => 'Office',
                                'wfh' => 'Work From Home',
                                default => $state ?? '-',
                            }),
                        TextEntry::make('source')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'web' => 'primary',
                                'mobile' => 'success',
                                'biometric' => 'warning',
                                'visit' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'web' => 'Web',
                                'mobile' => 'Mobile',
                                'biometric' => 'Biometric',
                                'visit' => 'Client Visit',
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
                                'visit' => 'success',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'present' => 'Present',
                                'late' => 'Late',
                                'half_day' => 'Half Day',
                                'absent' => 'Absent',
                                'leave' => 'Leave',
                                'holiday' => 'Holiday',
                                'visit' => 'Visit',
                                default => $state ?? '-',
                            }),
                        TextEntry::make('working_hours')
                            ->label('Working Hours')
                            ->state(function (AttendanceLog $record): ?string {
                                if (! $record->check_in_time || ! $record->check_out_time) {
                                    return null;
                                }
                                $diff = $record->check_in_time->diff($record->check_out_time);
                                $hours = ($diff->days * 24) + $diff->h;

                                return sprintf('%02d:%02d:%02d', $hours, $diff->i, $diff->s);
                            })
                            ->placeholder('N/A')
                            ->badge()
                            ->color('success'),
                    ]),

                // Visit Details Section — only shown for visit-sourced records
                Section::make('Client Visit Details')
                    ->icon('heroicon-o-map-pin')
                    ->iconColor('success')
                    ->columns(2)
                    ->visible(fn (AttendanceLog $record): bool => $record->source === 'visit' && $record->clientVisit !== null)
                    ->schema([
                        TextEntry::make('clientVisit.client_name')
                            ->label('Client Name')
                            ->icon('heroicon-o-building-office'),
                        TextEntry::make('clientVisit.client_address')
                            ->label('Client Address')
                            ->icon('heroicon-o-map-pin'),
                        TextEntry::make('clientVisit.purpose')
                            ->label('Visit Purpose')
                            ->badge()
                            ->color('primary')
                            ->formatStateUsing(fn (?string $state): string => $state ? (ClientVisit::getPurposeLabels()[$state] ?? $state) : '-'
                            ),
                        TextEntry::make('clientVisit.purpose_detail')
                            ->label('Purpose Detail')
                            ->placeholder('N/A'),
                        TextEntry::make('clientVisit.status')
                            ->label('Visit Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'planned' => 'warning',
                                'in_progress' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => $state ? (ClientVisit::getStatusLabels()[$state] ?? $state) : '-'
                            ),
                        TextEntry::make('clientVisit.visit_date')
                            ->label('Visit Date')
                            ->date(),
                        TextEntry::make('clientVisit.planned_start_time')
                            ->label('Planned Start')
                            ->placeholder('Not set'),
                        TextEntry::make('clientVisit.planned_end_time')
                            ->label('Planned End')
                            ->placeholder('Not set'),
                        TextEntry::make('clientVisit.actual_check_in')
                            ->label('Actual Check-In')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('Not checked in'),
                        TextEntry::make('clientVisit.actual_check_out')
                            ->label('Actual Check-Out')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('Not checked out'),
                        TextEntry::make('clientVisit.notes')
                            ->label('Notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                        TextEntry::make('clientVisit.visit_report')
                            ->label('Visit Report')
                            ->columnSpanFull()
                            ->placeholder('No report submitted'),
                        TextEntry::make('visit_check_in_coordinates')
                            ->label('Check-In Location')
                            ->state(fn (AttendanceLog $record): ?string => $record->clientVisit?->check_in_latitude && $record->clientVisit?->check_in_longitude
                                    ? "{$record->clientVisit->check_in_latitude}, {$record->clientVisit->check_in_longitude}"
                                    : null
                            )
                            ->placeholder('No GPS data')
                            ->url(fn (AttendanceLog $record): ?string => $record->clientVisit?->check_in_latitude && $record->clientVisit?->check_in_longitude
                                    ? "https://www.google.com/maps?q={$record->clientVisit->check_in_latitude},{$record->clientVisit->check_in_longitude}"
                                    : null
                            )
                            ->openUrlInNewTab()
                            ->icon('heroicon-o-globe-alt')
                            ->color('primary'),
                        TextEntry::make('visit_check_out_coordinates')
                            ->label('Check-Out Location')
                            ->state(fn (AttendanceLog $record): ?string => $record->clientVisit?->check_out_latitude && $record->clientVisit?->check_out_longitude
                                    ? "{$record->clientVisit->check_out_latitude}, {$record->clientVisit->check_out_longitude}"
                                    : null
                            )
                            ->placeholder('No GPS data')
                            ->url(fn (AttendanceLog $record): ?string => $record->clientVisit?->check_out_latitude && $record->clientVisit?->check_out_longitude
                                    ? "https://www.google.com/maps?q={$record->clientVisit->check_out_latitude},{$record->clientVisit->check_out_longitude}"
                                    : null
                            )
                            ->openUrlInNewTab()
                            ->icon('heroicon-o-globe-alt')
                            ->color('primary'),
                    ]),

                Section::make('Check-In Details')
                    ->columns(2)
                    ->schema([
                        Group::make([
                            TextEntry::make('check_in_time')
                                ->label('Check-In Time')
                                ->dateTime('d M Y, H:i:s')
                                ->icon('heroicon-o-clock'),
                            TextEntry::make('check_in_address')
                                ->label('Address')
                                ->placeholder('No address recorded')
                                ->icon('heroicon-o-map-pin'),
                            TextEntry::make('check_in_coordinates')
                                ->label('GPS Coordinates')
                                ->state(fn (AttendanceLog $record): ?string => $record->check_in_latitude && $record->check_in_longitude
                                        ? "{$record->check_in_latitude}, {$record->check_in_longitude}"
                                        : null
                                )
                                ->placeholder('No GPS data')
                                ->url(fn (AttendanceLog $record): ?string => $record->check_in_latitude && $record->check_in_longitude
                                        ? "https://www.google.com/maps?q={$record->check_in_latitude},{$record->check_in_longitude}"
                                        : null
                                )
                                ->openUrlInNewTab()
                                ->icon('heroicon-o-globe-alt')
                                ->color('primary'),
                        ]),
                        ImageEntry::make('check_in_photo_path')
                            ->label('Check-In Photo')
                            ->height(250)
                            ->extraImgAttributes([
                                'class' => 'cursor-zoom-in rounded-lg shadow-lg border',
                                'loading' => 'lazy',
                            ])
                            ->placeholder('No photo captured')
                            ->action(AttendanceLogResource::attendancePhotoModalAction('viewCheckInPhoto', 'Check-In Photo', 'check_in_photo_path'))
                            ->getStateUsing(function (AttendanceLog $record): ?string {
                                return AttendanceLogResource::resolveAttendancePhotoUrl($record->check_in_photo_path);
                            })
                            ->visible(fn (AttendanceLog $record): bool => filled($record->check_in_photo_path)),
                    ]),

                Section::make('Check-Out Details')
                    ->columns(2)
                    ->schema([
                        Group::make([
                            TextEntry::make('check_out_time')
                                ->label('Check-Out Time')
                                ->dateTime('d M Y, H:i:s')
                                ->placeholder('Not checked out yet')
                                ->icon('heroicon-o-clock'),
                            TextEntry::make('check_out_address')
                                ->label('Address')
                                ->placeholder('No address recorded')
                                ->icon('heroicon-o-map-pin'),
                            TextEntry::make('check_out_coordinates')
                                ->label('GPS Coordinates')
                                ->state(fn (AttendanceLog $record): ?string => $record->check_out_latitude && $record->check_out_longitude
                                        ? "{$record->check_out_latitude}, {$record->check_out_longitude}"
                                        : null
                                )
                                ->placeholder('No GPS data')
                                ->url(fn (AttendanceLog $record): ?string => $record->check_out_latitude && $record->check_out_longitude
                                        ? "https://www.google.com/maps?q={$record->check_out_latitude},{$record->check_out_longitude}"
                                        : null
                                )
                                ->openUrlInNewTab()
                                ->icon('heroicon-o-globe-alt')
                                ->color('primary'),
                        ]),
                        ImageEntry::make('check_out_photo_path')
                            ->label('Check-Out Photo')
                            ->height(250)
                            ->extraImgAttributes([
                                'class' => 'cursor-zoom-in rounded-lg shadow-lg border',
                                'loading' => 'lazy',
                            ])
                            ->placeholder('No photo captured')
                            ->action(AttendanceLogResource::attendancePhotoModalAction('viewCheckOutPhoto', 'Check-Out Photo', 'check_out_photo_path'))
                            ->getStateUsing(function (AttendanceLog $record): ?string {
                                return AttendanceLogResource::resolveAttendancePhotoUrl($record->check_out_photo_path);
                            })
                            ->visible(fn (AttendanceLog $record): bool => filled($record->check_out_photo_path)),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime('d M Y, H:i:s'),
                        TextEntry::make('updated_at')
                            ->dateTime('d M Y, H:i:s'),
                    ]),
            ]);
    }
}
