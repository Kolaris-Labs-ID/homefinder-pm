<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use App\Services\Hris\LeaveWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        $requests = $employee->leaveRequests()
            ->with('leaveType')
            ->latest()
            ->get()
            ->map(fn (LeaveRequest $leaveRequest) => [
                'id' => $leaveRequest->id,
                'leave_type' => $leaveRequest->leaveType->only(['id', 'code', 'name']),
                'start_date' => $leaveRequest->start_date,
                'end_date' => $leaveRequest->end_date,
                'total_days' => $leaveRequest->total_days,
                'status' => $leaveRequest->status,
                'reason' => $leaveRequest->reason,
                'has_attachment' => !empty($leaveRequest->attachment_path),
                'approved_at' => $leaveRequest->approved_at,
                'rejected_at' => $leaveRequest->rejected_at,
                'rejection_reason' => $leaveRequest->rejection_reason,
            ]);

        return response()->json([
            'data' => $requests,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        // First validate basic fields
        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        // Validate attachment if required by leave type (e.g., sick leave)
        if ($leaveType->requires_attachment) {
            $request->validate([
                'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ], [
                'attachment.required' => "A doctor's letter or medical certificate is required for {$leaveType->name}.",
            ]);
        } else {
            $request->validate([
                'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            ]);
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $balanceService = app(LeaveBalanceService::class);

        if (! $balanceService->canConsume($employee, $leaveType, $totalDays, $startDate)) {
            $reason = $balanceService->getIneligibilityReason($employee, $leaveType, $totalDays, $startDate);
            throw ValidationException::withMessages([
                'leave_type_id' => [$reason ?? 'Insufficient leave balance or not eligible.'],
            ]);
        }

        // Handle file upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "leave-attachments/{$employee->id}",
                'private'
            );
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => LeaveRequest::STATUS_DRAFT,
        ]);

        app(LeaveWorkflowService::class)->initializeWorkflow($leaveRequest);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data' => $leaveRequest->fresh('leaveType'),
        ], 201);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($leaveRequest->employee_id !== $employee->id, 403, 'You cannot cancel this request.');

        if (! in_array($leaveRequest->status, [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED], true)) {
            abort(422, 'Only pending or approved requests can be cancelled.');
        }

        if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
            app(LeaveBalanceService::class)->release($leaveRequest);
        }

        // Delete attachment file if exists
        if ($leaveRequest->attachment_path) {
            Storage::disk('private')->delete($leaveRequest->attachment_path);
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_CANCELLED,
            'attachment_path' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Leave request cancelled.',
        ]);
    }

    protected function resolveEmployee(Request $request): Employee
    {
        $employee = optional($request->user())->employee;

        abort_if(! $employee, 404, 'Employee profile not found.');

        return $employee;
    }
}

