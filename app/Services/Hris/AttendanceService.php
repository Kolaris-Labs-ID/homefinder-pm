<?php

namespace App\Services\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Maximum WFH days allowed per week.
     */
    const WFH_QUOTA_PER_WEEK = 1;

    /**
     * Standard work start time (configurable).
     */
    const WORK_START_HOUR = 9;
    const WORK_START_MINUTE = 0;

    /**
     * Grace period in minutes for late calculation.
     */
    const LATE_GRACE_PERIOD_MINUTES = 15;

    /**
     * Minimum work hours for full day (8 hours).
     */
    const FULL_DAY_HOURS = 8;

    /**
     * Minimum work hours for half day (4 hours).
     */
    const HALF_DAY_HOURS = 4;

    /**
     * Geocoding service for reverse geocoding GPS coordinates.
     */
    protected GeocodingService $geocodingService;

    /**
     * Photo service for processing attendance photos.
     */
    protected PhotoService $photoService;

    public function __construct(
        ?GeocodingService $geocodingService = null,
        ?PhotoService $photoService = null
    ) {
        $this->geocodingService = $geocodingService ?? new GeocodingService();
        $this->photoService = $photoService ?? new PhotoService();
    }

    /**
     * Safely reverse geocode coordinates with error handling.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int|null $employeeId For logging purposes
     * @return string
     */
    protected function safeReverseGeocode(float $latitude, float $longitude, ?int $employeeId = null): string
    {
        try {
            return $this->geocodingService->reverseGeocode($latitude, $longitude);
        } catch (\Exception $e) {
            \Log::warning('Geocoding failed', [
                'employee_id' => $employeeId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);
            // Return fallback address format
            return sprintf('Location (%.6f, %.6f)', $latitude, $longitude);
        }
    }

    /**
     * Get the remaining WFH quota for an employee in the current week.
     *
     * The week is calculated from Monday to Sunday.
     * Quota is 1 day per week and does not carry over.
     *
     * @param Employee $employee
     * @param Carbon|null $referenceDate Optional date to calculate week from (defaults to today)
     * @return int Remaining WFH days (0 or 1)
     */
    public function getRemainingWfhQuota(Employee $employee, ?Carbon $referenceDate = null): int
    {
        $date = $referenceDate ?? Carbon::today();

        // Get the start of the week (Monday) and end of the week (Sunday)
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY);

        // Count WFH days used in the current week
        $wfhDaysUsed = AttendanceLog::forEmployee($employee->id)
            ->forMode(AttendanceLog::MODE_WFH)
            ->forDateRange($weekStart, $weekEnd)
            ->whereNotNull('check_in_time')
            ->count();

        // Return remaining quota (minimum 0)
        return max(0, self::WFH_QUOTA_PER_WEEK - $wfhDaysUsed);
    }
    /**
     * Record employee check-in.
     *
     * @param Employee $employee
     * @param array $data Contains: source, mode, latitude?, longitude?, photo?
     * @return AttendanceLog
     * @throws \Exception If employee already checked in for the day
     * @throws \Exception If WFH mode selected but quota exhausted
     */
    public function checkIn(Employee $employee, array $data): AttendanceLog
    {
        $today = Carbon::today('Asia/Jakarta');
        $mode = $data['mode'] ?? AttendanceLog::MODE_OFFICE;

        // Validate employee not already checked in for today
        $existingLog = AttendanceLog::forEmployee($employee->id)
            ->forDate($today)
            ->whereNotNull('check_in_time')
            ->first();

        if ($existingLog) {
            throw new \Exception('Employee has already checked in for today.');
        }

        // Validate WFH quota if WFH mode is selected
        if ($mode === AttendanceLog::MODE_WFH) {
            $remainingQuota = $this->getRemainingWfhQuota($employee, $today);
            if ($remainingQuota <= 0) {
                throw new \Exception('WFH quota exhausted for this week.');
            }
        }

        // Process GPS coordinates for reverse geocoding
        // Use backend geocoding when possible, fall back to frontend-provided address
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $address = $data['address'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $geocodedAddress = $this->safeReverseGeocode((float) $latitude, (float) $longitude, $employee->id);
            // Only use geocoded result if it's a real address (not a coordinate fallback)
            if (!str_starts_with($geocodedAddress, 'Location (')) {
                $address = $geocodedAddress;
            }
        }

        // Process photo with timestamp overlay
        $photoPath = $data['photo_path'] ?? null;
        $photo = $data['photo'] ?? null;
        $checkInTime = Carbon::now('Asia/Jakarta');

        \Log::info('Check-in photo processing', [
            'employee_id' => $employee->id,
            'has_photo' => $photo !== null,
            'photo_type' => $photo ? get_class($photo) : 'null',
            'photo_is_uploaded_file' => $photo instanceof UploadedFile,
            'photo_path_provided' => $photoPath !== null,
        ]);

        if ($photo instanceof UploadedFile && $photoPath === null) {
            $photoAddress = $address ?? 'Unknown Location';
            \Log::info('Processing check-in photo', [
                'employee_id' => $employee->id,
                'photo_size' => $photo->getSize(),
                'photo_mime' => $photo->getMimeType(),
                'address' => $photoAddress,
            ]);
            try {
                $photoPath = $this->photoService->processAttendancePhoto(
                    $photo,
                    'in',
                    $checkInTime,
                    $photoAddress,
                    $employee->id
                );
                \Log::info('Check-in photo uploaded successfully', [
                    'employee_id' => $employee->id,
                    'photo_path' => $photoPath,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Photo processing failed during check-in', [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue without photo
                $photoPath = null;
            }
        }

        return DB::transaction(function () use ($employee, $data, $today, $mode, $latitude, $longitude, $address, $photoPath, $checkInTime) {
            return AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => $today,
                'mode' => $mode,
                'check_in_time' => $checkInTime,
                'check_in_latitude' => $latitude,
                'check_in_longitude' => $longitude,
                'check_in_address' => $address,
                'check_in_photo_path' => $photoPath,
                'source' => $data['source'] ?? AttendanceLog::SOURCE_WEB,
                'status' => AttendanceLog::STATUS_PRESENT,
            ]);
        });
    }

    /**
     * Record employee check-out.
     *
     * @param Employee $employee
     * @param array $data Contains: latitude?, longitude?, photo?
     * @return AttendanceLog
     * @throws \Exception If employee has not checked in for the day
     */
    public function checkOut(Employee $employee, array $data): AttendanceLog
    {
        $today = Carbon::today('Asia/Jakarta');

        // Find today's attendance log with check-in
        $attendanceLog = AttendanceLog::forEmployee($employee->id)
            ->forDate($today)
            ->whereNotNull('check_in_time')
            ->first();

        if (!$attendanceLog) {
            throw new \Exception('Employee has not checked in for today.');
        }

        if ($attendanceLog->hasCheckedOut()) {
            throw new \Exception('Employee has already checked out for today.');
        }

        // If employee is on break, end the break first
        if ($attendanceLog->isOnBreak()) {
            $this->endBreak($employee);
            $attendanceLog->refresh();
        }

        // Process GPS coordinates for reverse geocoding
        // Use backend geocoding when possible, fall back to frontend-provided address
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $address = $data['address'] ?? null;

        if ($latitude !== null && $longitude !== null) {
            $geocodedAddress = $this->safeReverseGeocode((float) $latitude, (float) $longitude, $attendanceLog->employee_id);
            // Only use geocoded result if it's a real address (not a coordinate fallback)
            if (!str_starts_with($geocodedAddress, 'Location (')) {
                $address = $geocodedAddress;
            }
        }

        // Process photo with timestamp overlay
        $photoPath = $data['photo_path'] ?? null;
        $photo = $data['photo'] ?? null;
        $checkOutTime = Carbon::now('Asia/Jakarta');

        \Log::info('Check-out photo processing', [
            'employee_id' => $attendanceLog->employee_id,
            'has_photo' => $photo !== null,
            'photo_type' => $photo ? get_class($photo) : 'null',
            'photo_is_uploaded_file' => $photo instanceof UploadedFile,
            'photo_path_provided' => $photoPath !== null,
        ]);

        if ($photo instanceof UploadedFile && $photoPath === null) {
            $photoAddress = $address ?? 'Unknown Location';
            \Log::info('Processing check-out photo', [
                'employee_id' => $attendanceLog->employee_id,
                'photo_size' => $photo->getSize(),
                'photo_mime' => $photo->getMimeType(),
                'address' => $photoAddress,
            ]);
            try {
                $photoPath = $this->photoService->processAttendancePhoto(
                    $photo,
                    'out',
                    $checkOutTime,
                    $photoAddress,
                    $attendanceLog->employee_id
                );
                \Log::info('Check-out photo uploaded successfully', [
                    'employee_id' => $attendanceLog->employee_id,
                    'photo_path' => $photoPath,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Photo processing failed during check-out', [
                    'employee_id' => $attendanceLog->employee_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Continue without photo
                $photoPath = null;
            }
        }

        return DB::transaction(function () use ($attendanceLog, $latitude, $longitude, $address, $photoPath, $checkOutTime) {
            $attendanceLog->update([
                'check_out_time' => $checkOutTime,
                'check_out_latitude' => $latitude,
                'check_out_longitude' => $longitude,
                'check_out_address' => $address,
                'check_out_photo_path' => $photoPath,
            ]);

            return $attendanceLog->fresh();
        });
    }

    /**
     * Start employee break.
     *
     * @param Employee $employee
     * @return AttendanceLog
     * @throws \Exception If employee has not checked in or already on break
     */
    public function startBreak(Employee $employee): AttendanceLog
    {
        $today = Carbon::today('Asia/Jakarta');

        // Find today's attendance log with check-in
        $attendanceLog = AttendanceLog::forEmployee($employee->id)
            ->forDate($today)
            ->whereNotNull('check_in_time')
            ->first();

        if (!$attendanceLog) {
            throw new \Exception('Employee has not checked in for today.');
        }

        if ($attendanceLog->hasCheckedOut()) {
            throw new \Exception('Employee has already checked out for today.');
        }

        if ($attendanceLog->isOnBreak()) {
            throw new \Exception('Employee is already on break.');
        }

        if ($attendanceLog->hasCompletedBreak()) {
            throw new \Exception('Employee has already taken a break today.');
        }

        return DB::transaction(function () use ($attendanceLog) {
            $attendanceLog->update([
                'break_start_time' => Carbon::now('Asia/Jakarta'),
            ]);

            return $attendanceLog->fresh();
        });
    }

    /**
     * End employee break.
     *
     * @param Employee $employee
     * @return AttendanceLog
     * @throws \Exception If employee is not on break
     */
    public function endBreak(Employee $employee): AttendanceLog
    {
        $today = Carbon::today('Asia/Jakarta');

        // Find today's attendance log with check-in
        $attendanceLog = AttendanceLog::forEmployee($employee->id)
            ->forDate($today)
            ->whereNotNull('check_in_time')
            ->first();

        if (!$attendanceLog) {
            throw new \Exception('Employee has not checked in for today.');
        }

        if (!$attendanceLog->isOnBreak()) {
            throw new \Exception('Employee is not currently on break.');
        }

        $breakEndTime = Carbon::now('Asia/Jakarta');
        $totalBreakMinutes = $attendanceLog->break_start_time->diffInMinutes($breakEndTime);

        return DB::transaction(function () use ($attendanceLog, $breakEndTime, $totalBreakMinutes) {
            $attendanceLog->update([
                'break_end_time' => $breakEndTime,
                'total_break_minutes' => $totalBreakMinutes,
            ]);

            return $attendanceLog->fresh();
        });
    }

    /**
     * Get today's attendance for an employee.
     *
     * @param Employee $employee
     * @param Carbon|null $date Optional date (defaults to today)
     * @return AttendanceLog|null
     */
    public function getTodayAttendance(Employee $employee, ?Carbon $date = null): ?AttendanceLog
    {
        $date = $date ?? Carbon::today();

        return AttendanceLog::forEmployee($employee->id)
            ->forDate($date)
            ->first();
    }

    /**
     * Get attendance history for an employee within a date range.
     *
     * Returns all AttendanceLogs within the specified range, ordered by date descending.
     *
     * @param Employee $employee
     * @param Carbon $from Start date (inclusive)
     * @param Carbon $to End date (inclusive)
     * @return Collection<AttendanceLog>
     */
    public function getHistory(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        return AttendanceLog::forEmployee($employee->id)
            ->forDateRange($from, $to)
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Compute the attendance status for an AttendanceLog.
     *
     * Status is determined based on:
     * - Check-in time (late if after work start + grace period)
     * - Check-out time (half day if worked less than full day hours)
     * - Leave records (if employee has approved leave for the date)
     *
     * @param AttendanceLog $log
     * @return string One of AttendanceLog::STATUS_* constants
     */
    public function computeStatus(AttendanceLog $log): string
    {
        // First, check if employee has approved leave for this date
        if ($this->hasApprovedLeave($log->employee_id, $log->date)) {
            return AttendanceLog::STATUS_LEAVE;
        }

        // If no check-in, employee is absent
        if (!$log->hasCheckedIn()) {
            return AttendanceLog::STATUS_ABSENT;
        }

        // Calculate work start time for the day
        $workStartTime = $log->date->copy()->setTime(
            self::WORK_START_HOUR,
            self::WORK_START_MINUTE,
            0
        );

        // Add grace period
        $lateThreshold = $workStartTime->copy()->addMinutes(self::LATE_GRACE_PERIOD_MINUTES);

        // Check if late (check-in after work start + grace period)
        $isLate = $log->check_in_time->gt($lateThreshold);

        // If checked out, calculate work duration
        if ($log->hasCheckedOut()) {
            $workDurationHours = $log->check_in_time->diffInMinutes($log->check_out_time) / 60;

            // Half day if worked less than full day but at least half day hours
            if ($workDurationHours < self::FULL_DAY_HOURS && $workDurationHours >= self::HALF_DAY_HOURS) {
                return AttendanceLog::STATUS_HALF_DAY;
            }

            // If worked less than half day hours, could be considered half day or absent
            // For now, we'll consider it half day if they showed up at all
            if ($workDurationHours < self::HALF_DAY_HOURS) {
                return AttendanceLog::STATUS_HALF_DAY;
            }
        }

        // If late, return late status
        if ($isLate) {
            return AttendanceLog::STATUS_LATE;
        }

        // Default to present
        return AttendanceLog::STATUS_PRESENT;
    }

    /**
     * Check if an employee has approved leave for a specific date.
     *
     * @param int $employeeId
     * @param Carbon $date
     * @return bool
     */
    protected function hasApprovedLeave(int $employeeId, Carbon $date): bool
    {
        return LeaveRequest::where('employee_id', $employeeId)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Get direct reports for a manager.
     *
     * Returns employees who have the given employee as their manager
     * in their active primary position.
     *
     * @param Employee $manager
     * @return Collection<Employee>
     */
    public function getDirectReports(Employee $manager): Collection
    {
        return Employee::whereHas('positions', function ($query) use ($manager) {
            $query->where('manager_id', $manager->id)
                ->where('is_primary', true)
                ->whereNull('end_date'); // Active positions only
        })->get();
    }

    /**
     * Get team attendance for a manager's direct reports.
     *
     * Returns attendance summaries for all direct reports of the manager
     * for the specified date.
     *
     * @param Employee $manager
     * @param Carbon|null $date Optional date (defaults to today)
     * @return Collection Array of attendance summaries with employee info
     */
    public function getTeamAttendance(Employee $manager, ?Carbon $date = null): Collection
    {
        $date = $date ?? Carbon::today();

        // Get direct reports
        $directReports = $this->getDirectReports($manager);

        // Build attendance summaries for each direct report
        return $directReports->map(function (Employee $employee) use ($date) {
            $attendanceLog = AttendanceLog::forEmployee($employee->id)
                ->forDate($date)
                ->first();

            return [
                'employee_id' => $employee->id,
                'employee' => $employee,
                'date' => $date->toDateString(),
                'attendance_log' => $attendanceLog,
                'status' => $attendanceLog ? $this->computeStatus($attendanceLog) : AttendanceLog::STATUS_ABSENT,
                'check_in_time' => $attendanceLog?->check_in_time,
                'check_out_time' => $attendanceLog?->check_out_time,
                'mode' => $attendanceLog?->mode,
            ];
        });
    }

    /**
     * Get aggregated team attendance statistics for a manager's direct reports.
     *
     * Returns aggregated statistics including present days, late days, and absent days
     * for all direct reports within the specified period.
     *
     * @param Employee $manager
     * @param Carbon $from Start date (inclusive)
     * @param Carbon $to End date (inclusive)
     * @return array{
     *     total_employees: int,
     *     period_days: int,
     *     present_days: int,
     *     late_days: int,
     *     absent_days: int,
     *     half_days: int,
     *     leave_days: int,
     *     by_employee: Collection
     * }
     */
    public function getTeamStatistics(Employee $manager, Carbon $from, Carbon $to): array
    {
        // Get direct reports
        $directReports = $this->getDirectReports($manager);

        // Calculate period days (excluding weekends if needed - for now include all)
        $periodDays = $from->diffInDays($to) + 1;

        // Initialize counters
        $totalPresent = 0;
        $totalLate = 0;
        $totalAbsent = 0;
        $totalHalfDay = 0;
        $totalLeave = 0;

        // Calculate statistics per employee
        $byEmployee = $directReports->map(function (Employee $employee) use ($from, $to, &$totalPresent, &$totalLate, &$totalAbsent, &$totalHalfDay, &$totalLeave) {
            // Get all attendance logs for this employee in the period
            $logs = AttendanceLog::forEmployee($employee->id)
                ->forDateRange($from, $to)
                ->get();

            // Count by status
            $presentDays = 0;
            $lateDays = 0;
            $absentDays = 0;
            $halfDays = 0;
            $leaveDays = 0;

            foreach ($logs as $log) {
                $status = $this->computeStatus($log);

                switch ($status) {
                    case AttendanceLog::STATUS_PRESENT:
                        $presentDays++;
                        $totalPresent++;
                        break;
                    case AttendanceLog::STATUS_LATE:
                        $lateDays++;
                        $totalLate++;
                        break;
                    case AttendanceLog::STATUS_ABSENT:
                        $absentDays++;
                        $totalAbsent++;
                        break;
                    case AttendanceLog::STATUS_HALF_DAY:
                        $halfDays++;
                        $totalHalfDay++;
                        break;
                    case AttendanceLog::STATUS_LEAVE:
                        $leaveDays++;
                        $totalLeave++;
                        break;
                }
            }

            return [
                'employee_id' => $employee->id,
                'employee' => $employee,
                'present_days' => $presentDays,
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'half_days' => $halfDays,
                'leave_days' => $leaveDays,
                'total_logged_days' => $logs->count(),
            ];
        });

        return [
            'total_employees' => $directReports->count(),
            'period_days' => $periodDays,
            'present_days' => $totalPresent,
            'late_days' => $totalLate,
            'absent_days' => $totalAbsent,
            'half_days' => $totalHalfDay,
            'leave_days' => $totalLeave,
            'by_employee' => $byEmployee,
        ];
    }
}
