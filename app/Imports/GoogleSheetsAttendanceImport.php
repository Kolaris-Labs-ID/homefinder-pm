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

/**
 * Import attendance logs from Google Sheets format
 *
 * Expected columns:
 * - No (row number)
 * - Name (employee name)
 * - Action (Check In / Check Out)
 * - Date & Time
 * - Location
 * - Address
 * - City
 * - State/Province
 * - Country
 * - Postal Code
 * - Latitude
 * - Longitude
 * - Photo URL
 * - Work Hours
 * - Description
 */
class GoogleSheetsAttendanceImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors, SkipsFailures;

    protected $importedCount = 0;
    protected $skippedCount = 0;
    protected $attendanceCache = [];

    public function model(array $row)
    {
        // Skip empty rows
        if (empty($row['name']) || empty($row['action']) || empty($row['date_time'])) {
            $this->skippedCount++;
            return null;
        }

        // Find employee by name
        $employee = $this->findEmployeeByName(trim($row['name']));
        if (!$employee) {
            $this->skippedCount++;
            return null;
        }

        // Parse date and time
        $dateTime = $this->parseDateTime($row['date_time']);
        if (!$dateTime) {
            $this->skippedCount++;
            return null;
        }

        $date = $dateTime->format('Y-m-d');
        $action = strtolower(trim($row['action']));

        // Build full address from components
        $fullAddress = $this->buildFullAddress($row);

        // Get or create attendance log for this employee and date
        $cacheKey = $employee->id . '_' . $date;

        if (!isset($this->attendanceCache[$cacheKey])) {
            $this->attendanceCache[$cacheKey] = AttendanceLog::forEmployee($employee->id)
                ->forDate($date)
                ->first();
        }

        $attendanceLog = $this->attendanceCache[$cacheKey];

        if ($action === 'check in' || $action === 'checkin' || $action === 'in') {
            // Handle check-in
            if ($attendanceLog) {
                // Update existing record with check-in data
                $attendanceLog->update([
                    'check_in_time' => $dateTime,
                    'check_in_latitude' => $this->parseCoordinate($row['latitude'] ?? null),
                    'check_in_longitude' => $this->parseCoordinate($row['longitude'] ?? null),
                    'check_in_address' => $fullAddress,
                    'check_in_photo_path' => $this->parsePhotoUrl($row['photo_url'] ?? null),
                ]);
                $this->importedCount++;
                return null;
            } else {
                // Create new record with check-in data
                $attendanceLog = new AttendanceLog([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'check_in_time' => $dateTime,
                    'check_in_latitude' => $this->parseCoordinate($row['latitude'] ?? null),
                    'check_in_longitude' => $this->parseCoordinate($row['longitude'] ?? null),
                    'check_in_address' => $fullAddress,
                    'check_in_photo_path' => $this->parsePhotoUrl($row['photo_url'] ?? null),
                    'source' => AttendanceLog::SOURCE_IMPORT,
                    'status' => AttendanceLog::STATUS_PRESENT,
                ]);
                $this->attendanceCache[$cacheKey] = $attendanceLog;
                $this->importedCount++;
                return $attendanceLog;
            }
        } elseif ($action === 'check out' || $action === 'checkout' || $action === 'out') {
            // Handle check-out
            if ($attendanceLog) {
                // Update existing record with check-out data
                $attendanceLog->update([
                    'check_out_time' => $dateTime,
                    'check_out_latitude' => $this->parseCoordinate($row['latitude'] ?? null),
                    'check_out_longitude' => $this->parseCoordinate($row['longitude'] ?? null),
                    'check_out_address' => $fullAddress,
                    'check_out_photo_path' => $this->parsePhotoUrl($row['photo_url'] ?? null),
                ]);
                $this->importedCount++;
                return null;
            } else {
                // Create new record with check-out data only (unusual but handle it)
                $attendanceLog = new AttendanceLog([
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'check_out_time' => $dateTime,
                    'check_out_latitude' => $this->parseCoordinate($row['latitude'] ?? null),
                    'check_out_longitude' => $this->parseCoordinate($row['longitude'] ?? null),
                    'check_out_address' => $fullAddress,
                    'check_out_photo_path' => $this->parsePhotoUrl($row['photo_url'] ?? null),
                    'source' => AttendanceLog::SOURCE_IMPORT,
                    'status' => AttendanceLog::STATUS_PRESENT,
                ]);
                $this->attendanceCache[$cacheKey] = $attendanceLog;
                $this->importedCount++;
                return $attendanceLog;
            }
        }

        $this->skippedCount++;
        return null;
    }

    protected function findEmployeeByName(string $name): ?Employee
    {
        // Try to find employee by user name
        return Employee::whereHas('user', function ($query) use ($name) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        })->first();
    }

    protected function parseDateTime($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            $dateTimeString = trim($value);

            // Handle Excel serial numbers
            if (is_numeric($dateTimeString)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateTimeString)
                );
            }

            // Try common datetime formats
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d H:i',
                'd/m/Y H:i:s',
                'd/m/Y H:i',
                'm/d/Y H:i:s',
                'm/d/Y H:i',
                'd-m-Y H:i:s',
                'd-m-Y H:i',
            ];

            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, $dateTimeString);
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Fallback to Carbon's flexible parsing
            return Carbon::parse($dateTimeString);
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

    protected function parsePhotoUrl($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $url = trim($value);

        // Validate it's a URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return null;
    }

    protected function buildFullAddress(array $row): ?string
    {
        $parts = array_filter([
            trim($row['address'] ?? ''),
            trim($row['city'] ?? ''),
            trim($row['state_province'] ?? ''),
            trim($row['country'] ?? ''),
            trim($row['postal_code'] ?? ''),
        ]);

        if (empty($parts)) {
            return trim($row['location'] ?? '') ?: null;
        }

        return implode(', ', $parts);
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'action' => 'required',
            'date_time' => 'required',
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
