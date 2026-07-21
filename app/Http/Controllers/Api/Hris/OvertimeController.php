<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OvertimeController extends Controller
{
    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    /**
     * GET /api/hris/overtime
     * List all overtime logs for the authenticated employee (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $query = OvertimeLog::query()
            ->where('user_id', $employee->user_id)
            ->with('manager:id,name')
            ->latest('start_time');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->get()->map(fn (OvertimeLog $log) => $this->formatLog($log));

        return response()->json(['data' => $logs]);
    }

    // -------------------------------------------------------------------------
    // Active session
    // -------------------------------------------------------------------------

    /**
     * GET /api/hris/overtime/active
     * Return the currently running overtime session (if any).
     */
    public function active(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $log = OvertimeLog::query()
            ->where('user_id', $employee->user_id)
            ->running()
            ->with('manager:id,name')
            ->first();

        return response()->json(['data' => $log ? $this->formatLog($log) : null]);
    }

    // -------------------------------------------------------------------------
    // Start
    // -------------------------------------------------------------------------

    /**
     * POST /api/hris/overtime/start
     */
    public function start(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        // Prevent double-running sessions
        $running = OvertimeLog::query()
            ->where('user_id', $employee->user_id)
            ->running()
            ->first();

        if ($running) {
            return response()->json([
                'message' => 'You already have an active overtime session. Stop it before starting a new one.',
                'data' => $this->formatLog($running),
            ], 422);
        }

        $data = $request->validate([
            'manager_id' => ['required', 'exists:users,id'],
            'task_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'client_name' => ['nullable', 'string', 'max:255'],
        ]);

        // UI ESTIMATE ONLY. The payable rate is re-resolved per work date at batch
        // generation (OvertimePayService) — this value is never the payment basis.
        $hourlyRate = 0;
        $salary = $employee->activeSalaryStructure;
        if ($salary && $salary->basic_salary > 0) {
            $hourlyRate = $salary->overtime_hourly_rate;
        }

        $log = OvertimeLog::create([
            'user_id' => $employee->user_id,
            'manager_id' => $data['manager_id'],
            'task_name' => $data['task_name'],
            'description' => $data['description'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'start_time' => now(),
            'hourly_rate' => $hourlyRate,
            'status' => OvertimeLog::STATUS_RUNNING,
        ]);

        Log::info('Overtime started', [
            'user_id' => $employee->user_id,
            'log_id' => $log->id,
            'task_name' => $log->task_name,
            'hourly_rate' => $log->hourly_rate,
        ]);

        return response()->json([
            'message' => 'Overtime session started.',
            'data' => $this->formatLog($log->load('manager:id,name')),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Stop
    // -------------------------------------------------------------------------

    /**
     * POST /api/hris/overtime/stop
     * Accepts multipart/form-data because evidence photo may be attached.
     */
    public function stop(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $log = OvertimeLog::query()
            ->where('user_id', $employee->user_id)
            ->running()
            ->first();

        Log::info('Overtime stop request', [
            'user_id' => $employee->user_id,
            'log_found' => $log ? $log->id : null,
            'has_client' => $log?->hasClient(),
            'has_file' => $request->hasFile('evidence'),
            'all_files' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
        ]);

        abort_if(! $log, 404, 'No active overtime session found.');

        // Evidence is required when a client was set
        $evidenceRule = $log->hasClient() ? ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'] : ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'];

        $data = $request->validate([
            'evidence' => $evidenceRule,
        ]);

        $evidencePath = null;
        if ($request->hasFile('evidence')) {
            $evidencePath = $this->storeEvidence($request, $employee->user_id, $log->id);
        }

        $endTime = now();
        $durationMinutes = (int) $log->start_time->diffInMinutes($endTime);

        $log->update([
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'evidence_path' => $evidencePath,
            'status' => OvertimeLog::STATUS_PENDING,
        ]);

        Log::info('Overtime stopped', [
            'user_id' => $employee->user_id,
            'log_id' => $log->id,
            'duration_minutes' => $durationMinutes,
        ]);

        return response()->json([
            'message' => 'Overtime session stopped and submitted for approval.',
            'data' => $this->formatLog($log->fresh()->load('manager:id,name')),
        ]);
    }

    // -------------------------------------------------------------------------
    // Managers dropdown
    // -------------------------------------------------------------------------

    /**
     * GET /api/hris/overtime/managers
     * Returns active employees that can be selected as manager/assigner.
     */
    public function managers(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $managers = Employee::query()
            ->active()
            ->with(['user:id,name', 'primaryPosition.position'])
            ->where('user_id', '!=', $currentUser->id)
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->user_id,
                'name' => $e->user->name ?? '—',
                'position' => optional(optional($e->primaryPosition)->position)->name,
            ])
            ->sortBy('name')
            ->values();

        return response()->json(['data' => $managers]);
    }

    // -------------------------------------------------------------------------
    // Show single
    // -------------------------------------------------------------------------

    public function show(Request $request, OvertimeLog $overtimeLog): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($overtimeLog->user_id !== $employee->user_id, 403, 'Forbidden.');

        return response()->json(['data' => $this->formatLog($overtimeLog->load('manager:id,name'))]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function storeEvidence(Request $request, int $userId, int $logId): string
    {
        $file = $request->file('evidence');

        // Compress to max 1200px wide using Intervention Image if available
        $resizedBinary = $this->compressImage($file->getPathname(), 1200);

        $year = now()->format('Y');
        $month = now()->format('m');
        $dir = "overtime-evidence/{$year}/{$month}";
        $name = "user_{$userId}_log_{$logId}_".uniqid().'.jpg';
        $filename = "{$dir}/{$name}";

        if ($resizedBinary !== null) {
            Storage::disk('local')->put($filename, $resizedBinary);
        } else {
            $file->storeAs($dir, $name, 'local');
        }

        return $filename;
    }

    protected function compressImage(string $path, int $maxWidth): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $info = getimagesize($path);
            if (! $info) {
                return null;
            }

            [$origWidth, $origHeight] = $info;
            $mime = $info['mime'];

            $src = match ($mime) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
                'image/png' => imagecreatefrompng($path),
                'image/webp' => imagecreatefromwebp($path),
                default => null,
            };

            if (! $src) {
                return null;
            }

            if ($origWidth <= $maxWidth) {
                // Still re-encode to JPEG at 80% quality for compression
                ob_start();
                imagejpeg($src, null, 80);
                imagedestroy($src);

                return ob_get_clean();
            }

            $ratio = $maxWidth / $origWidth;
            $newHeight = (int) ($origHeight * $ratio);
            $dst = imagecreatetruecolor($maxWidth, $newHeight);

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($src);

            ob_start();
            imagejpeg($dst, null, 80);
            imagedestroy($dst);

            return ob_get_clean();
        } catch (\Throwable $e) {
            Log::warning('OvertimeController: image compression failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    protected function formatLog(OvertimeLog $log): array
    {
        $durationMinutes = $log->status === OvertimeLog::STATUS_RUNNING
            ? $log->computeDuration()
            : $log->duration_minutes;

        return [
            'id' => $log->id,
            'task_name' => $log->task_name,
            'description' => $log->description,
            'client_name' => $log->client_name,
            'manager' => $log->relationLoaded('manager')
                ? ['id' => $log->manager?->id, 'name' => $log->manager?->name]
                : ['id' => $log->manager_id, 'name' => null],
            'start_time' => $log->start_time?->toIso8601String(),
            'end_time' => $log->end_time?->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'hourly_rate' => (float) $log->hourly_rate,
            'estimated_pay' => $log->calculateEstimatedPay($durationMinutes),
            'evidence_path' => $log->evidence_path,
            'status' => $log->status,
            'status_label' => $log->status_label,
            'rejection_note' => $log->rejection_note,
            'auto_closed' => $log->auto_closed,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = optional($request->user())->employee;
        abort_if(! $employee, 404, 'Employee profile not found.');

        return $employee;
    }
}
