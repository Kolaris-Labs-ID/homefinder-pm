<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Services\Hris\LeaveBalanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TeamController extends Controller
{
    /**
     * Get all team members with their current status.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today();

            // Get all active employees with their user info and position
            $employees = Employee::with(['user', 'primaryPosition.position'])
                ->active()
                ->get();

            $teamMembers = $employees->map(function (Employee $employee) use ($today) {
                // Get today's attendance
                $attendance = AttendanceLog::where('employee_id', $employee->id)
                    ->whereDate('date', $today)
                    ->first();

                // Get active leave request for today
                $leaveRequest = LeaveRequest::where('employee_id', $employee->id)
                    ->where('status', LeaveRequest::STATUS_APPROVED)
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today)
                    ->with('leaveType')
                    ->first();

                // Get active client visit for today (only if table exists)
                $clientVisit = null;
                if (Schema::hasTable('client_visits')) {
                    try {
                        $clientVisit = ClientVisit::where('employee_id', $employee->id)
                            ->whereDate('visit_date', $today)
                            ->whereIn('status', [ClientVisit::STATUS_PLANNED, ClientVisit::STATUS_IN_PROGRESS])
                            ->first();
                    } catch (\Exception $e) {
                        // Silently ignore if table doesn't exist or has issues
                    }
                }

                // Determine current status
                $status = $this->determineStatus($attendance, $leaveRequest, $clientVisit);

                return [
                    'id' => $employee->id,
                    'name' => $employee->user->name ?? 'Unknown',
                    'email' => $employee->user->email ?? null,
                    'phone' => $employee->phone ?? null,
                    'avatar_url' => $employee->user->avatar_url ?? null,
                    'position' => $employee->primaryPosition?->position?->title ?? null,
                    'department' => null, // Position model doesn't have department relationship
                    'status' => $status['status'],
                    'status_label' => $status['label'],
                    'status_detail' => $status['detail'],
                    'certificate_status' => $status['certificate_status'] ?? null,
                    'work_mode' => $attendance?->check_in_time ? $attendance->mode : null,
                    'check_in_time' => $attendance?->check_in_time ? Carbon::parse($attendance->check_in_time)->format('H:i:s') : null,
                    'is_on_break' => $attendance && $attendance->break_start_time && !$attendance->break_end_time,
                    'mood_emoji' => $employee->user->mood_emoji ?? null,
                    'mood_text' => $employee->user->mood_text ?? null,
                ];
            });

            // Sort: on duty first, then by name
            $sorted = $teamMembers->sortBy([
                fn ($a, $b) => $this->getStatusPriority($a['status']) <=> $this->getStatusPriority($b['status']),
                fn ($a, $b) => $a['name'] <=> $b['name'],
            ])->values();

            return response()->json([
                'data' => $sorted,
                'summary' => [
                    'total' => $teamMembers->count(),
                    'on_duty' => $teamMembers->where('status', 'on_duty')->count(),
                    'on_break' => $teamMembers->where('status', 'on_break')->count(),
                    'on_leave' => $teamMembers->where('status', 'on_leave')->count(),
                    'visiting' => $teamMembers->where('status', 'visiting')->count(),
                    'offline' => $teamMembers->where('status', 'offline')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load team data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Determine employee's current status.
     */
    private function determineStatus(?AttendanceLog $attendance, ?LeaveRequest $leave, ?ClientVisit $visit): array
    {
        // Priority: Leave > Visit (in progress) > Break > On Duty > Offline

        // Check if on approved leave
        if ($leave) {
            // Sick Leave gets its own label (rather than the generic "On Leave") so the
            // team tab's Letter Pending badge reads naturally next to it; other leave
            // types are unaffected.
            $isSick = ($leave->leaveType->code ?? null) === LeaveBalanceService::CODE_SICK;

            return [
                'status' => 'on_leave',
                'label' => $isSick ? 'On Sick Leave' : 'On Leave',
                'detail' => $leave->leaveType->name ?? 'Leave',
                'certificate_status' => $leave->certificate_status,
            ];
        }

        // Check if visiting client (in progress)
        if ($visit && $visit->status === ClientVisit::STATUS_IN_PROGRESS) {
            return [
                'status' => 'visiting',
                'label' => 'Visiting Client',
                'detail' => $visit->client_name . ' - ' . ($visit->purpose_label ?? $visit->purpose),
            ];
        }

        // Check attendance
        if ($attendance && $attendance->check_in_time) {
            // Already checked out
            if ($attendance->check_out_time) {
                return [
                    'status' => 'offline',
                    'label' => 'Checked Out',
                    'detail' => 'Left at ' . Carbon::parse($attendance->check_out_time)->format('H:i'),
                ];
            }

            // On break
            if ($attendance->break_start_time && !$attendance->break_end_time) {
                return [
                    'status' => 'on_break',
                    'label' => 'On Break',
                    'detail' => 'Since ' . Carbon::parse($attendance->break_start_time)->format('H:i'),
                ];
            }

            // Working
            return [
                'status' => 'on_duty',
                'label' => 'On Duty',
                'detail' => 'Since ' . Carbon::parse($attendance->check_in_time)->format('H:i'),
            ];
        }

        // Check if has planned visit today
        if ($visit && $visit->status === ClientVisit::STATUS_PLANNED) {
            return [
                'status' => 'visiting',
                'label' => 'Planned Visit',
                'detail' => $visit->client_name,
            ];
        }

        // Not checked in yet
        return [
            'status' => 'offline',
            'label' => 'Offline',
            'detail' => 'Not checked in',
        ];
    }

    /**
     * Get status priority for sorting (lower = higher priority).
     */
    private function getStatusPriority(string $status): int
    {
        return match ($status) {
            'on_duty' => 1,
            'on_break' => 2,
            'visiting' => 3,
            'on_leave' => 4,
            'offline' => 5,
            default => 6,
        };
    }
}
