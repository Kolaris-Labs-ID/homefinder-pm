<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class LeaveRequestActionHandler
{
    /**
     * HR accepts an uploaded medical certificate. The leave itself is already
     * approved and stays that way — attendance/payroll read leave_requests.status,
     * which this does not touch.
     */
    public static function verifyCertificate(LeaveRequest $leaveRequest, User $verifier): void
    {
        if ($leaveRequest->certificate_status !== LeaveRequest::CERT_STATUS_SUBMITTED) {
            throw new \Exception('This certificate has not been submitted for review.');
        }

        $leaveRequest->update([
            'certificate_status' => LeaveRequest::CERT_STATUS_VERIFIED,
            'certificate_verified_at' => now(),
            'certificate_verified_by' => $verifier->id,
        ]);
    }

    /**
     * HR rejects an uploaded certificate (e.g. illegible scan) without rejecting
     * the leave. Within the 48h window the employee may resubmit (rejected);
     * past the deadline the request returns to the overdue resolution queue
     * instead — see SickLeaveResolutionService.
     */
    public static function rejectCertificate(LeaveRequest $leaveRequest, User $verifier, string $reason): void
    {
        if ($leaveRequest->certificate_status !== LeaveRequest::CERT_STATUS_SUBMITTED) {
            throw new \Exception('This certificate has not been submitted for review.');
        }

        $isPastDeadline = $leaveRequest->certificate_deadline_at !== null
            && $leaveRequest->certificate_deadline_at->isPast();

        $leaveRequest->update([
            'certificate_status' => $isPastDeadline
                ? LeaveRequest::CERT_STATUS_OVERDUE
                : LeaveRequest::CERT_STATUS_REJECTED,
            'certificate_verified_at' => now(),
            'certificate_verified_by' => $verifier->id,
            'certificate_rejection_reason' => $reason,
        ]);
    }

    /**
     * HR resolves an overdue-certificate sick leave: quota-covered days convert
     * to an auto-approved Annual Leave request, the remainder becomes alpha.
     * The service owns the transaction and idempotency guards.
     */
    public static function resolveCertificate(LeaveRequest $leaveRequest, User $resolver): array
    {
        return app(\App\Services\Hris\SickLeaveResolutionService::class)->resolve($leaveRequest, $resolver);
    }

    public static function approve(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                return;
            }

            app(LeaveBalanceService::class)->consume($leaveRequest);

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_APPROVED,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        });
    }

    public static function reject(LeaveRequest $leaveRequest, string $reason): void
    {
        DB::transaction(function () use ($leaveRequest, $reason) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                app(LeaveBalanceService::class)->release($leaveRequest);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_REJECTED,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }

    public static function cancel(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest) {
            if ($leaveRequest->status === LeaveRequest::STATUS_APPROVED) {
                app(LeaveBalanceService::class)->release($leaveRequest);
            }

            $leaveRequest->update([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        });
    }
}
