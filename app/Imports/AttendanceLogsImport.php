<?php

namespace App\Imports;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Carbon\Carbon;

class AttendanceLogsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $skippedCount = 0;

    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['employee_email']) || empty($row['date'])) {
            $this->skippedCount++;
            return null;
        }

        // Find employee by email
        $employee = Employee::whereHas('user', function ($query) use ($row) {
            $query->where('email', trim($row['employee_email']));
        })->first();

        if (!$employee) {
            $this->skippedCount++;
            return null;
        }

        // Parse date
        $date = $this->parseDate($row['date']);
        if (!$date) {
            $this->skippedCount++;
            return null;
        }

        // Check if attendance log already exists for this employee and date
        $existingLog = AttendanceLog::forEmployee($employee->id)
            ->forDate($date)
            ->first();

        if ($existingLog) {
            // Update existing log
            $existingLog->update($this->buildAttendanceData($row, $employee, $date));
            $this->importedCount++;
            return null; // Return null since we updated, not created
        }

        // Create new attendance log
        $this->importedCount++;
        return new AttendanceLog($this->buildAttendanceData($row, $employee, $date));
    }

    protected function buildAttendanceData(array $row, Employee $employee, Carbon $date): array
    {
        $checkInTime = $this->parseDateTime($row['check_in_time'] ?? null, $date);
        $checkOutTime = $this->parseDateTime($row['check_out_time'] ?? null, $date);

        // Determine mode
        $mode = strtolower(trim($row['mode'] ?? 'office'));
        if (!in_array($mode, AttendanceLog::getModes())) {
            $mode = AttendanceLog::MODE_OFFICE;
        }

        // Determine status
        $status = strtolower(trim($row['status'] ?? 'present'));
        if (!in_array($status, AttendanceLog::getStatuses())) {
            $status = AttendanceLog::STATUS_PRESENT;
        }

        // Determine source - default to 'import' for imported records
        $source = strtolower(trim($row['source'] ?? 'import'));
        if (!in_array($source, AttendanceLog::getSources())) {
            $source = AttendanceLog::SOURCE_IMPORT;
        }

        return [
            'employee_id' => $employee->id,
            'date' => $date,
            'mode' => $mode,
            'check_in_time' => $checkInTime,
            'check_out_time' => $checkOutTime,
            'check_in_latitude' => $this->parseCoordinate($row['check_in_latitude'] ?? null),
            'check_in_longitude' => $this->parseCoordinate($row['check_in_longitude'] ?? null),
            'check_in_address' => trim($row['check_in_address'] ?? '') ?: null,
            'check_out_latitude' => $this->parseCoordinate($row['check_out_latitude'] ?? null),
            'check_out_longitude' => $this->parseCoordinate($row['check_out_longitude'] ?? null),
            'check_out_address' => trim($row['check_out_address'] ?? '') ?: null,
            'source' => $source,
            'status' => $status,
        ];
    }

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            $dateString = trim($value);

            // Handle Excel serial numbers
            if (is_numeric($dateString)) {
                return Carbon::createFromFormat(
                    'Y-m-d',
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString)->format('Y-m-d')
                );
            }

            // Try common date formats
            $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd-m-Y', 'm-d-Y'];
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $dateString)->startOfDay();
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Fallback to Carbon's flexible parsing
            return Carbon::parse($dateString)->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseDateTime($value, Carbon $date): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            $timeString = trim($value);

            // Handle Excel serial numbers (time portion)
            if (is_numeric($timeString)) {
                $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($timeString);
                return $date->copy()->setTime(
                    (int) $excelDate->format('H'),
                    (int) $excelDate->format('i'),
                    (int) $excelDate->format('s')
                );
            }

            // Try to parse as time only (HH:MM or HH:MM:SS)
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeString, $matches)) {
                $hour = (int) $matches[1];
                $minute = (int) $matches[2];
                $second = isset($matches[3]) ? (int) $matches[3] : 0;
                return $date->copy()->setTime($hour, $minute, $second);
            }

            // Try to parse as full datetime
            $parsed = Carbon::parse($timeString);
            return $date->copy()->setTime($parsed->hour, $parsed->minute, $parsed->second);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function parseCoordinate($value): ?float
    {
        if (empty($value) || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    public function rules(): array
    {
        return [
            'employee_email' => 'required',
            'date' => 'required',
            'check_in_time' => 'nullable',
            'check_out_time' => 'nullable',
            'mode' => 'nullable',
            'status' => 'nullable',
            'source' => 'nullable',
            'check_in_latitude' => 'nullable',
            'check_in_longitude' => 'nullable',
            'check_in_address' => 'nullable',
            'check_out_latitude' => 'nullable',
            'check_out_longitude' => 'nullable',
            'check_out_address' => 'nullable',
        ];
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
