<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    protected AttendanceService $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Record employee check-in.
     *
     * POST /api/hris/attendance/check-in
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        try {
            // Log incoming request data for debugging
            \Log::info('Check-in request received', [
                'employee_id' => $employee->id,
                'has_file_photo' => $request->hasFile('photo'),
                'all_files' => array_keys($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'all_input_keys' => array_keys($request->all()),
            ]);

            $data = $request->validate([
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'address' => ['nullable', 'string', 'max:500'],
                'source' => ['nullable', Rule::in([
                    AttendanceLog::SOURCE_WEB,
                    AttendanceLog::SOURCE_MOBILE,
                    AttendanceLog::SOURCE_BIOMETRIC,
                ])],
                'mode' => ['nullable', Rule::in([
                    AttendanceLog::MODE_OFFICE,
                    AttendanceLog::MODE_WFH,
                ])],
                'photo' => ['nullable', 'image', 'max:5120'], // Max 5MB
            ]);

            // Explicitly add the photo file from request (validation doesn't include files in array)
            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo');
                \Log::info('Photo file found in request', [
                    'employee_id' => $employee->id,
                    'photo_size' => $request->file('photo')->getSize(),
                    'photo_mime' => $request->file('photo')->getMimeType(),
                    'photo_original_name' => $request->file('photo')->getClientOriginalName(),
                ]);
            }

            $attendanceLog = $this->attendanceService->checkIn($employee, $data);

            // Compute status for the attendance log
            $status = $this->attendanceService->computeStatus($attendanceLog);
            $attendanceLog->status = $status;

            return response()->json([
                'message' => 'Check-in recorded successfully.',
                'data' => $this->formatAttendanceLog($attendanceLog),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Business logic errors (duplicate check-in, WFH quota exhausted) should return 422
            $businessErrors = [
                'Employee has already checked in for today.',
                'WFH quota exhausted for this week.',
            ];

            if (in_array($e->getMessage(), $businessErrors)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            \Log::error('Attendance check-in failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily attendance for the authenticated employee.
     *
     * GET /api/hris/attendance/daily?date=YYYY-MM-DD
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $data = $request->validate([
                'date' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::today();
            $attendanceLog = $this->attendanceService->getTodayAttendance($employee, $date);

            if (!$attendanceLog) {
                return response()->json([
                    'message' => 'No attendance record found for the specified date.',
                    'data' => null,
                ]);
            }

            // Compute status for the attendance log
            $status = $this->attendanceService->computeStatus($attendanceLog);
            $attendanceLog->status = $status;

            return response()->json([
                'data' => $this->formatAttendanceLog($attendanceLog),
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Attendance daily fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch daily attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get remaining WFH quota for the current week.
     *
     * GET /api/hris/attendance/wfh-quota
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function wfhQuota(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $remainingQuota = $this->attendanceService->getRemainingWfhQuota($employee);

            return response()->json([
                'data' => [
                    'remaining_quota' => $remainingQuota,
                    'max_quota_per_week' => AttendanceService::WFH_QUOTA_PER_WEEK,
                ],
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('WFH quota fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch WFH quota: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance history for the authenticated employee.
     *
     * GET /api/hris/attendance/history?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $data = $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $from = Carbon::parse($data['from']);
            $to = Carbon::parse($data['to']);

            $attendanceLogs = $this->attendanceService->getHistory($employee, $from, $to);

            // Compute status for each attendance log
            $formattedLogs = $attendanceLogs->map(function (AttendanceLog $log) {
                $log->status = $this->attendanceService->computeStatus($log);
                return $this->formatAttendanceLog($log);
            });

            return response()->json([
                'data' => $formattedLogs,
            ]);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Attendance history fetch failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to fetch attendance history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record employee check-out.
     *
     * POST /api/hris/attendance/check-out
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        try {
            // Log incoming request data for debugging
            \Log::info('Check-out request received', [
                'employee_id' => $employee->id,
                'has_file_photo' => $request->hasFile('photo'),
                'all_files' => array_keys($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'all_input_keys' => array_keys($request->all()),
            ]);

            $data = $request->validate([
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'address' => ['nullable', 'string', 'max:500'],
                'photo' => ['nullable', 'image', 'max:5120'], // Max 5MB
            ]);

            // Explicitly add the photo file from request (validation doesn't include files in array)
            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo');
                \Log::info('Photo file found in request', [
                    'employee_id' => $employee->id,
                    'photo_size' => $request->file('photo')->getSize(),
                    'photo_mime' => $request->file('photo')->getMimeType(),
                    'photo_original_name' => $request->file('photo')->getClientOriginalName(),
                ]);
            }

            $attendanceLog = $this->attendanceService->checkOut($employee, $data);

            // Compute status for the attendance log
            $status = $this->attendanceService->computeStatus($attendanceLog);
            $attendanceLog->status = $status;

            return response()->json([
                'message' => 'Check-out recorded successfully.',
                'data' => $this->formatAttendanceLog($attendanceLog),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Business logic errors should return 422
            $businessErrors = [
                'Employee has not checked in for today.',
                'Employee has already checked out for today.',
            ];

            if (in_array($e->getMessage(), $businessErrors)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            \Log::error('Attendance check-out failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start employee break.
     *
     * POST /api/hris/attendance/break/start
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function startBreak(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        try {
            $attendanceLog = $this->attendanceService->startBreak($employee);

            // Compute status for the attendance log
            $status = $this->attendanceService->computeStatus($attendanceLog);
            $attendanceLog->status = $status;

            return response()->json([
                'message' => 'Break started successfully.',
                'data' => $this->formatAttendanceLog($attendanceLog),
            ]);
        } catch (\Exception $e) {
            \Log::error('Start break failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * End employee break.
     *
     * POST /api/hris/attendance/break/end
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function endBreak(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        try {
            $attendanceLog = $this->attendanceService->endBreak($employee);

            // Compute status for the attendance log
            $status = $this->attendanceService->computeStatus($attendanceLog);
            $attendanceLog->status = $status;

            return response()->json([
                'message' => 'Break ended successfully.',
                'data' => $this->formatAttendanceLog($attendanceLog),
            ]);
        } catch (\Exception $e) {
            \Log::error('End break failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve the employee from the authenticated user.
     *
     * @param Request $request
     * @return Employee
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function resolveEmployee(Request $request): Employee
    {
        $user = $request->user();

        if (!$user) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Unauthenticated.',
                ], 401)
            );
        }

        $employee = $user->employee;

        if (!$employee) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Employee profile not found. Please contact HR to set up your employee record.',
                ], 404)
            );
        }

        return $employee;
    }

    /**
     * Format an AttendanceLog for API response.
     *
     * @param AttendanceLog $log
     * @return array
     */
    protected function formatAttendanceLog(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'employee_id' => $log->employee_id,
            'date' => $log->date->format('Y-m-d'),
            'mode' => $log->mode,
            'source' => $log->source,
            'status' => $log->status,
            'check_in' => [
                'time' => $log->check_in_time?->toIso8601String(),
                'latitude' => $log->check_in_latitude,
                'longitude' => $log->check_in_longitude,
                'address' => $log->check_in_address,
                'photo_path' => $log->check_in_photo_path,
            ],
            'check_out' => [
                'time' => $log->check_out_time?->toIso8601String(),
                'latitude' => $log->check_out_latitude,
                'longitude' => $log->check_out_longitude,
                'address' => $log->check_out_address,
                'photo_path' => $log->check_out_photo_path,
            ],
            'break' => [
                'start_time' => $log->break_start_time?->toIso8601String(),
                'end_time' => $log->break_end_time?->toIso8601String(),
                'total_minutes' => $log->total_break_minutes,
                'is_on_break' => $log->isOnBreak(),
            ],
            'created_at' => $log->created_at->toIso8601String(),
            'updated_at' => $log->updated_at->toIso8601String(),
        ];
    }
}
