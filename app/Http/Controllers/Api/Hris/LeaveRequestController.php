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

        $data = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $balanceService = app(LeaveBalanceService::class);

        if (! $balanceService->canConsume($employee, $leaveType, $totalDays, $startDate)) {
            throw ValidationException::withMessages([
                'leave_type_id' => ['Insufficient leave balance or not eligible.'],
            ]);
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? null,
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

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_CANCELLED,
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

