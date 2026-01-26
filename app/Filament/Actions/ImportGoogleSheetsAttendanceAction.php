<?php

namespace App\Filament\Actions;

use App\Imports\GoogleSheetsAttendanceImport;
use App\Exports\GoogleSheetsAttendanceTemplateExport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ImportGoogleSheetsAttendanceAction
{
    public static function make(): Action
    {
        return Action::make('import_google_sheets_attendance')
            ->label(__('Import from Google Sheets'))
            ->icon('heroicon-m-arrow-up-tray')
            ->color('primary')
            ->form([
                Section::make(__('Import Attendance from Google Sheets Format'))
                    ->description(__('Upload your Google Sheets attendance data. This importer accepts the format: No, Name, Action, Date & Time, Location, Address, City, State/Province, Country, Postal Code, Latitude, Longitude, Photo URL, Work Hours, Description.'))
                    ->schema([
                        Actions::make([
                            FormAction::make('download_template')
                                ->label(__('Download Google Sheets Template'))
                                ->icon('heroicon-m-arrow-down-tray')
                                ->color('info')
                                ->action(function () {
                                    return Excel::download(
                                        new GoogleSheetsAttendanceTemplateExport(),
                                        'google-sheets-attendance-template.xlsx'
                                    );
                                })
                        ])->fullWidth(),

                        FileUpload::make('excel_file')
                            ->label(__('Excel/CSV File'))
                            ->helperText(__('Upload your Google Sheets file exported as Excel or CSV. Required columns: Name, Action (Check In/Check Out), Date & Time. The system will automatically match check-ins and check-outs by employee and date.'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->disk('local')
                            ->directory('temp-imports')
                            ->visibility('private')
                            ->preserveFilenames(false)
                            ->storeFileNamesIn('original_filename'),
                    ]),
            ])
            ->action(function (array $data) {
                // Handle file path - FileUpload returns the relative path including directory
                $filePath = $data['excel_file'];

                // FileUpload may return path with or without directory prefix
                // Normalize to just use what FileUpload returns directly
                $relativePath = $filePath;

                // Check if file exists using Storage facade first
                if (!Storage::disk('local')->exists($relativePath)) {
                    // Log for debugging
                    \Log::error('Import file not found', [
                        'filePath' => $filePath,
                        'relativePath' => $relativePath,
                        'diskRoot' => config('filesystems.disks.local.root'),
                        'fullPath' => Storage::disk('local')->path($relativePath),
                    ]);

                    Notification::make()
                        ->title(__('Import Error'))
                        ->body(__('Uploaded file not found. Please try again.'))
                        ->danger()
                        ->send();
                    return;
                }

                // Get the full path for Excel import
                $filePath = Storage::disk('local')->path($relativePath);

                try {
                    $import = new GoogleSheetsAttendanceImport();
                    Excel::import($import, $filePath);

                    $importedCount = $import->getImportedCount();
                    $skippedCount = $import->getSkippedCount();
                    $errors = $import->errors();
                    $failures = $import->failures();

                    // Clean up uploaded file
                    Storage::disk('local')->delete($relativePath);

                    if ($importedCount > 0) {
                        $message = __("Successfully imported/updated :count attendance record(s).", ['count' => $importedCount]);

                        if ($skippedCount > 0) {
                            $message .= ' ' . __(':count row(s) were skipped.', ['count' => $skippedCount]);
                        }

                        $failuresCount = $failures instanceof \Illuminate\Support\Collection ? $failures->count() : count($failures);
                        $errorsCount = $errors instanceof \Illuminate\Support\Collection ? $errors->count() : count($errors);

                        if ($errorsCount > 0 || $failuresCount > 0) {
                            $message .= ' ' . __('Some rows had errors.');
                        }

                        Notification::make()
                            ->title(__('Import Completed'))
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        $errorDetails = [];

                        $failuresArray = $failures instanceof \Illuminate\Support\Collection ? $failures->all() : (array) $failures;
                        $errorsArray = $errors instanceof \Illuminate\Support\Collection ? $errors->all() : (array) $errors;

                        if (count($failuresArray) > 0) {
                            $errorDetails[] = __('Validation errors found in :count row(s):', ['count' => count($failuresArray)]);
                            foreach (array_slice($failuresArray, 0, 5) as $failure) {
                                $errorDetails[] = __('Row :row: :errors', [
                                    'row' => $failure->row(),
                                    'errors' => implode(', ', $failure->errors())
                                ]);
                            }
                            if (count($failuresArray) > 5) {
                                $errorDetails[] = __('... and :count more errors', ['count' => count($failuresArray) - 5]);
                            }
                        }

                        if (count($errorsArray) > 0) {
                            $errorDetails[] = __('Processing errors:');
                            foreach (array_slice($errorsArray, 0, 5) as $error) {
                                $errorDetails[] = $error->getMessage();
                            }
                        }

                        if (empty($errorDetails)) {
                            $errorDetails = [
                                __('Common issues to check:'),
                                __('• Ensure employee names match names in HRIS'),
                                __('• Verify Action column contains "Check In" or "Check Out"'),
                                __('• Check Date & Time format (YYYY-MM-DD HH:MM:SS or DD/MM/YYYY HH:MM:SS)'),
                                __('• Ensure Name, Action, and Date & Time columns are not empty'),
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
                    if (Storage::disk('local')->exists($relativePath)) {
                        Storage::disk('local')->delete($relativePath);
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
