<?php

namespace App\Http\Controllers\Api\Hris;

use App\Exceptions\Hris\AttendanceBusinessException;
use App\Http\Controllers\Controller;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\AttendanceSetting;
use App\Models\Hris\Employee;
use App\Models\User;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                    AttendanceLog::MODE_WFA,
                ])],
                'photo' => ['nullable', 'image', 'max:5120'], // Max 5MB
                'captured_at' => ['nullable', 'date'],
                'client_event_id' => ['nullable', 'string', 'max:64'],
                'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'source_platform' => ['nullable', Rule::in(AttendanceLog::getSourcePlatforms())],
                'location_provider' => ['nullable', Rule::in(AttendanceLog::getLocationProviders())],
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

            // Exact replays are successful without repeating status side effects.
            if (! $attendanceLog->wasRecentlyCreated) {
                return response()->json([
                    'message' => 'Check-in already recorded.',
                    'data' => $this->formatAttendanceLog($attendanceLog),
                ], 200);
            }

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
                return response()->json(array_filter([
                    'message' => $e->getMessage(),
                    // Offline replay needs to tell "already recorded" apart from a real
                    // rejection so it can settle the queued item as synced, not failed.
                    'code' => $e->getMessage() === 'Employee has already checked in for today.'
                        ? 'duplicate_attendance'
                        : null,
                ]), 422);
            }

            \Log::error('Attendance check-in failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to record check-in. Please try again.',
            ], 500);
        }
    }

    /**
     * Get daily attendance for the authenticated employee.
     *
     * GET /api/hris/attendance/daily?date=YYYY-MM-DD
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $data = $request->validate([
                'date' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $date = isset($data['date']) ? Carbon::parse($data['date'], 'Asia/Jakarta') : Carbon::today('Asia/Jakarta');
            $attendanceLog = $this->attendanceService->getTodayAttendance($employee, $date);

            if (! $attendanceLog) {
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
                'message' => 'Failed to fetch daily attendance. Please try again.',
            ], 500);
        }
    }

    /**
     * Get remaining WFH quota for the current week.
     *
     * GET /api/hris/attendance/wfh-quota
     */
    public function wfhQuota(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $remainingQuota = $this->attendanceService->getRemainingWfhQuota($employee);

            return response()->json([
                'data' => [
                    'remaining_quota' => $remainingQuota,
                    'max_quota_per_week' => AttendanceSetting::getSettings()->wfh_quota_per_week,
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
                'message' => 'Failed to fetch WFH quota. Please try again.',
            ], 500);
        }
    }

    /**
     * Get attendance history for the authenticated employee.
     *
     * GET /api/hris/attendance/history?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $employee = $this->resolveEmployee($request);

            $data = $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $from = Carbon::parse($data['from'], 'Asia/Jakarta');
            $to = Carbon::parse($data['to'], 'Asia/Jakarta');

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
                'message' => 'Failed to fetch attendance history. Please try again.',
            ], 500);
        }
    }

    /**
     * Record employee check-out.
     *
     * POST /api/hris/attendance/check-out
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
                'captured_at' => ['nullable', 'date'],
                'client_event_id' => ['nullable', 'string', 'max:64'],
                'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
                'source_platform' => ['nullable', Rule::in(AttendanceLog::getSourcePlatforms())],
                'location_provider' => ['nullable', Rule::in(AttendanceLog::getLocationProviders())],
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

            return response()->json([
                'message' => 'Check-out recorded successfully.',
                'data' => $this->formatAttendanceLog($attendanceLog),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (AttendanceBusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            // Business logic errors should return 422
            $businessErrors = [
                'Employee has not checked in for today.',
                'Employee has already checked out for today.',
            ];

            if (in_array($e->getMessage(), $businessErrors)) {
                return response()->json(array_filter([
                    'message' => $e->getMessage(),
                    'code' => $e->getMessage() === 'Employee has already checked out for today.'
                        ? 'duplicate_attendance'
                        : null,
                ]), 422);
            }

            \Log::error('Attendance check-out failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to record check-out. Please try again.',
            ], 500);
        }
    }

    /**
     * Start employee break.
     *
     * POST /api/hris/attendance/break/start
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
        } catch (AttendanceBusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Start break failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to start break. Please try again.',
            ], 500);
        }
    }

    /**
     * End employee break.
     *
     * POST /api/hris/attendance/break/end
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
        } catch (AttendanceBusinessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('End break failed', [
                'user_id' => $request->user()?->id,
                'employee_id' => $employee->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to end break. Please try again.',
            ], 500);
        }
    }

    /**
     * Resolve the employee from the authenticated user.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function resolveEmployee(Request $request): Employee
    {
        $user = $request->user();

        if (! $user) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Unauthenticated.',
                ], 401)
            );
        }

        $employee = $user->employee;

        if (! $employee) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'message' => 'Employee profile not found. Please contact HR to set up your employee record.',
                ], 404)
            );
        }

        return $employee;
    }

    /**
     * Serve an attendance photo from local storage.
     *
     * GET /api/hris/attendance/photo?path=...&token=...
     *
     * Accepts auth via either:
     * - Standard Authorization header (Bearer token)
     * - Query parameter "token" (for use in <Image> components)
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function servePhoto(Request $request)
    {
        // Authenticate via query token (needed for <Image> components that can't send headers)
        $token = $request->query('token');
        if (! $token) {
            abort(401, 'Authentication required.');
        }

        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        if (! $accessToken || ! $accessToken->tokenable) {
            abort(401, 'Invalid token.');
        }

        $path = $request->query('path');

        if (! $path) {
            abort(404, 'Photo not found.');
        }

        // Validate before any Storage call: Flysystem throws PathTraversalDetected
        // (an unhandled 500) if a ../ traversal reaches exists()/get() first.
        if (! $this->isSafeAttendancePhotoPath($path)) {
            abort(403, 'Access denied.');
        }

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'Photo not found.');
        }

        $user = $accessToken->tokenable;
        $employee = $user instanceof User ? $user->employee : null;

        if (! $employee) {
            abort(403, 'Access denied.');
        }

        $ownsPhoto = AttendanceLog::forEmployee($employee->id)
            ->where(function ($query) use ($path) {
                $query->where('check_in_photo_path', $path)
                    ->orWhere('check_out_photo_path', $path);
            })
            ->exists();

        if (! $ownsPhoto) {
            abort(403, 'Access denied.');
        }

        $file = Storage::disk('local')->get($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * Format an AttendanceLog for API response.
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
                'time' => $this->formatAsJakartaIso($log->check_in_time),
                'latitude' => $log->check_in_latitude,
                'longitude' => $log->check_in_longitude,
                'address' => $log->check_in_address,
                'photo_path' => $this->resolvePhotoUrl($log->check_in_photo_path),
                'captured_at' => $this->formatAsJakartaIso($log->check_in_captured_at),
                'client_event_id' => $log->check_in_client_event_id,
                'accuracy_meters' => $log->check_in_accuracy_meters,
                'source_platform' => $log->check_in_source_platform,
                'location_provider' => $log->check_in_location_provider,
            ],
            'check_out' => [
                'time' => $this->formatAsJakartaIso($log->check_out_time),
                'latitude' => $log->check_out_latitude,
                'longitude' => $log->check_out_longitude,
                'address' => $log->check_out_address,
                'photo_path' => $this->resolvePhotoUrl($log->check_out_photo_path),
                'captured_at' => $this->formatAsJakartaIso($log->check_out_captured_at),
                'client_event_id' => $log->check_out_client_event_id,
                'accuracy_meters' => $log->check_out_accuracy_meters,
                'source_platform' => $log->check_out_source_platform,
                'location_provider' => $log->check_out_location_provider,
            ],
            'break' => [
                'start_time' => $this->formatAsJakartaIso($log->break_start_time),
                'end_time' => $this->formatAsJakartaIso($log->break_end_time),
                'total_minutes' => $log->total_break_minutes,
                'is_on_break' => $log->isOnBreak(),
            ],
            'created_at' => $log->created_at->toIso8601String(),
            'updated_at' => $log->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format a datetime as ISO 8601 in Asia/Jakarta timezone.
     *
     * The DB stores Jakarta local times, but if APP_TIMEZONE is UTC,
     * Eloquent reads them back as UTC (wrong). This helper re-interprets
     * the raw datetime values as Jakarta, producing correct ISO output
     * regardless of APP_TIMEZONE setting.
     */
    protected function formatAsJakartaIso(?\Carbon\Carbon $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $time->format('Y-m-d H:i:s'),
            'Asia/Jakarta'
        )->toIso8601String();
    }

    /**
     * Resolve a photo path to a full URL.
     *
     * If the path is already a full URL (e.g. Supabase), return as-is.
     * If it's a local storage path, generate an API URL to serve it.
     */
    protected function resolvePhotoUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        // Already a full URL (Supabase or other external storage)
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Local path — generate API URL to serve it
        return url('/api/hris/attendance/photo').'?'.http_build_query(['path' => $path]);
    }

    /**
     * Reject directory traversal (including percent-encoded/double-encoded
     * variants) before the path ever reaches a Storage call.
     */
    protected function isSafeAttendancePhotoPath(string $path): bool
    {
        $normalized = rawurldecode(rawurldecode($path));

        if (str_contains($normalized, "\0") || str_contains($normalized, '..') || str_contains($normalized, '\\')) {
            return false;
        }

        return str_starts_with($normalized, 'attendance-photos/');
    }
}
