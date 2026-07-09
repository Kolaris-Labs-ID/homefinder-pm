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
                'has_attachment' => ! empty($leaveRequest->attachment_path),
                'approved_at' => $leaveRequest->approved_at,
                'rejected_at' => $leaveRequest->rejected_at,
                'rejection_reason' => $leaveRequest->rejection_reason,
                'certificate_status' => $leaveRequest->certificate_status,
                'certificate_deadline_at' => $leaveRequest->certificate_deadline_at,
                'certificate_submitted_at' => $leaveRequest->certificate_submitted_at,
                'certificate_verified_at' => $leaveRequest->certificate_verified_at,
                'certificate_rejection_reason' => $leaveRequest->certificate_rejection_reason,
                'certificate_resolution' => $leaveRequest->certificate_resolution,
                'converted_from_leave_request_id' => $leaveRequest->converted_from_leave_request_id,
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
            'reason' => ['required', 'string'],
        ]);

        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        // Sick Leave uses provisional approval: no attachment required at submission,
        // HR approves immediately and the employee gets a 48h window to upload the
        // certificate (see LeaveRequest::markAsApproved()). Every other requires_attachment
        // leave type keeps the original enforce-at-submission rule unchanged.
        if ($leaveType->requires_attachment && $leaveType->code !== LeaveBalanceService::CODE_SICK) {
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

    /**
     * Upload (or resubmit) the medical certificate for a provisionally-approved leave.
     * Reuses the same storage disk/path/mime-validation convention as store().
     */
    public function uploadCertificate(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->resolveEmployee($request);

        abort_if($leaveRequest->employee_id !== $employee->id, 403, 'You cannot upload a certificate for this leave request.');

        abort_if($leaveRequest->status !== LeaveRequest::STATUS_APPROVED, 422, 'Only approved leave requests can receive a certificate.');

        // pending/rejected: within the normal 48h window. overdue: deadline passed
        // but HR hasn't resolved yet — a late letter is still accepted, and HR can
        // verify it instead of converting the leave. Only verified/submitted/resolved
        // states (and legacy expired) block re-upload.
        if (! in_array($leaveRequest->certificate_status, [
            LeaveRequest::CERT_STATUS_PENDING,
            LeaveRequest::CERT_STATUS_REJECTED,
            LeaveRequest::CERT_STATUS_OVERDUE,
        ], true)) {
            abort(422, 'This leave request is not awaiting a certificate upload.');
        }

        $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachmentPath = $request->file('attachment')->store(
            "leave-attachments/{$employee->id}",
            'private'
        );

        // Guarded write: re-checks state in the UPDATE itself so a concurrent HR
        // resolution (overdue -> resolved) between our guard read and this write
        // can't be clobbered back to 'submitted'.
        $updated = LeaveRequest::whereKey($leaveRequest->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereIn('certificate_status', [
                LeaveRequest::CERT_STATUS_PENDING,
                LeaveRequest::CERT_STATUS_REJECTED,
                LeaveRequest::CERT_STATUS_OVERDUE,
            ])
            ->update([
                'attachment_path' => $attachmentPath,
                'certificate_status' => LeaveRequest::CERT_STATUS_SUBMITTED,
                'certificate_submitted_at' => now(),
                'certificate_rejection_reason' => null,
            ]);

        if (! $updated) {
            Storage::disk('private')->delete($attachmentPath);
            abort(422, 'This leave request is not awaiting a certificate upload.');
        }

        $leaveRequest->refresh();

        $this->notifyHRCertificateSubmitted($leaveRequest);

        return response()->json([
            'message' => 'Certificate uploaded successfully.',
            'data' => $leaveRequest->fresh('leaveType'),
        ]);
    }

    /**
     * Same HR-role lookup convention as CheckExpiringCertifications::getHRUsers().
     */
    protected function notifyHRCertificateSubmitted(LeaveRequest $leaveRequest): void
    {
        $employeeName = $leaveRequest->employee->user?->name ?? 'Unknown';

        $hrUsers = \App\Models\User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['hr-admin', 'hr-manager']);
        })->get();

        foreach ($hrUsers as $hrUser) {
            \App\Models\Notification::create([
                'user_id' => $hrUser->id,
                'type' => 'certificate_submitted_hr',
                'title' => 'Certificate Awaiting Review',
                'message' => "{$employeeName} submitted a medical certificate for review.",
                'data' => [
                    'leave_request_id' => $leaveRequest->id,
                    'employee_id' => $leaveRequest->employee_id,
                    'employee_name' => $employeeName,
                ],
            ]);
        }
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
