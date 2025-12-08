<?php

namespace App\Filament\Actions;

use App\Imports\AttendanceLogsImport;
use App\Exports\AttendanceLogTemplateExport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ImportAttendanceLogsAction
{
    public static function make(): Action
    {
        return Action::make('import_attendance_logs')
            ->label(__('Import Attendance Logs'))
            ->icon('heroicon-m-arrow-up-tray')
            ->color('success')
            ->form([
                Section::make(__('Import Attendance Logs from Excel'))
                    ->description(__('Upload an Excel file to import attendance logs. Download the template below to see the required format.'))
                    ->schema([
                        Actions::make([
                            FormAction::make('download_template')
                                ->label(__('Download Import Template'))
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('info')
                                ->action(function () {
                                    return Excel::download(
                                        new AttendanceLogTemplateExport(),
                                        'attendance-log-import-template.xlsx'
                                    );
                                })
                        ])->fullWidth(),

                        FileUpload::make('excel_file')
                            ->label(__('Excel File'))
                            ->helperText(__('Upload the Excel file with attendance data. Required columns: employee_email, date. Optional: check_in_time, check_out_time, mode, status, source, GPS coordinates, addresses.'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('local')
                            ->directory('temp-imports')
                            ->visibility('private'),
                    ]),
            ])
            ->action(function (array $data) {
                // Handle file path - FileUpload returns filename as string
                $fileName = $data['excel_file'];

                // Build the full path
                $filePath = Storage::disk('local')->path('temp-imports/' . $fileName);

                // Check if file exists
                if (!file_exists($filePath)) {
                    Notification::make()
                        ->title(__('Import Error'))
                        ->body(__('Uploaded file not found. Please try again.'))
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $import = new AttendanceLogsImport();
                    Excel::import($import, $filePath);

                    $importedCount = $import->getImportedCount();
                    $skippedCount = $import->getSkippedCount();
                    $errors = $import->errors();
                    $failures = $import->failures();

                    // Clean up uploaded file
                    Storage::disk('local')->delete('temp-imports/' . $fileName);

                    if ($importedCount > 0) {
                        $message = __("Successfully imported/updated :count attendance log(s).", ['count' => $importedCount]);

                        if ($skippedCount > 0) {
                            $message .= ' ' . __(':count row(s) were skipped.', ['count' => $skippedCount]);
                        }

                        if (count($errors) > 0 || count($failures) > 0) {
                            $message .= ' ' . __('Some rows had errors.');
                        }

                        Notification::make()
                            ->title(__('Import Completed'))
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        $errorDetails = [];

                        if (count($failures) > 0) {
                            $errorDetails[] = __('Validation errors found in :count row(s):', ['count' => count($failures)]);
                            foreach (array_slice($failures, 0, 5) as $failure) {
                                $errorDetails[] = __('Row :row: :errors', [
                                    'row' => $failure->row(),
                                    'errors' => implode(', ', $failure->errors())
                                ]);
                            }
                            if (count($failures) > 5) {
                                $errorDetails[] = __('... and :count more errors', ['count' => count($failures) - 5]);
                            }
                        }

                        if (count($errors) > 0) {
                            $errorDetails[] = __('Processing errors:');
                            foreach (array_slice($errors, 0, 5) as $error) {
                                $errorDetails[] = $error->getMessage();
                            }
                        }

                        if (empty($errorDetails)) {
                            $errorDetails = [
                                __('Common issues to check:'),
                                __('• Ensure employee emails exist in the system'),
                                __('• Verify date format is YYYY-MM-DD'),
                                __('• Check time format is HH:MM or HH:MM:SS'),
                                __('• Ensure mode is "office" or "wfh"'),
                                __('• Ensure status is valid (present, late, half_day, absent, leave, holiday)'),
                            ];
                        }

                        Notification::make()
                            ->title(__('Import Failed'))
                            ->body(implode("\n", $errorDetails))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                } catch (\Exception $e) {
                    // Clean up uploaded file on error
                    if (Storage::disk('local')->exists('temp-imports/' . $fileName)) {
                        Storage::disk('local')->delete('temp-imports/' . $fileName);
                    }

                    Notification::make()
                        ->title(__('Import Error'))
                        ->body(__('An error occurred during import: :message', ['message' => $e->getMessage()]))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
