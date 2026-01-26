<?php

namespace App\Filament\Resources\AttendanceLogResource\Pages;

use App\Exports\AttendanceLogsExport;
use App\Filament\Actions\ImportAttendanceLogsAction;
use App\Filament\Actions\ImportGoogleSheetsAttendanceAction;
use App\Filament\Resources\AttendanceLogResource;
use Filament\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListAttendanceLogs extends ListRecords
{
    protected static string $resource = AttendanceLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportGoogleSheetsAttendanceAction::make(),
            ImportAttendanceLogsAction::make(),
            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Section::make('Export Options')
                        ->description('Select columns and format for export')
                        ->schema([
                            Select::make('format')
                                ->label('Format')
                                ->options([
                                    'xlsx' => 'Excel (XLSX)',
                                    'csv' => 'CSV',
                                ])
                                ->default('xlsx')
                                ->required(),
                            CheckboxList::make('columns')
                                ->label('Columns')
                                ->options([
                                    'employee' => 'Employee',
                                    'date' => 'Date',
                                    'mode' => 'Mode',
                                    'check_in_time' => 'Check-In Time',
                                    'check_out_time' => 'Check-Out Time',
                                    'check_in_address' => 'Check-In Address',
                                    'check_out_address' => 'Check-Out Address',
                                    'check_in_latitude' => 'Check-In Latitude',
                                    'check_in_longitude' => 'Check-In Longitude',
                                    'check_out_latitude' => 'Check-Out Latitude',
                                    'check_out_longitude' => 'Check-Out Longitude',
                                    'source' => 'Source',
                                    'status' => 'Status',
                                ])
                                ->default(['employee', 'date', 'mode', 'check_in_time', 'check_out_time', 'status'])
                                ->required()
                                ->minItems(1)
                                ->columns(2),
                        ]),
                ])
                ->action(function (array $data) {
                    $filename = 'attendance_logs_' . now()->format('Y-m-d_His');
                    $extension = $data['format'] === 'csv' ? 'csv' : 'xlsx';

                    return Excel::download(
                        new AttendanceLogsExport($this->getFilteredTableQuery()->get(), $data['columns']),
                        "{$filename}.{$extension}"
                    );
                }),
        ];
    }
}
