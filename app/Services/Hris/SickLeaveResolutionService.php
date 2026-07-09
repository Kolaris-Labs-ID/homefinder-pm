<?php

namespace App\Services\Hris;

use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SickLeaveResolutionService
{
    public function __construct(
        protected ApprovalWorkflowService $approvalWorkflowService,
        protected LeaveBalanceService $leaveBalanceService,
    ) {}

    /**
     * Resolve an overdue-certificate sick leave: convert as many days as the
     * employee's annual quota covers into a linked auto-approved Annual Leave
     * request; the remainder becomes alpha (covered by no approved leave, so
     * payroll prorates it and pays no daily allowance).
     *
     * total_days is calendar days (diffInDays + 1) — the split mirrors the
     * consume()/release() convention, charging calendar days.
     *
     * @return array{resolution:string,converted_days:int,alpha_days:int,annual_leave_request_id:int|null}
     */
    public function resolve(LeaveRequest $leaveRequest, User $resolver): array
    {
        $result = DB::transaction(function () use ($leaveRequest, $resolver): array {
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->first();

            if (! $locked
                || $locked->status !== LeaveRequest::STATUS_APPROVED
                || $locked->certificate_status !== LeaveRequest::CERT_STATUS_OVERDUE
            ) {
                throw new \Exception('This leave request is not awaiting certificate resolution.');
            }

            $locked->loadMissing(['employee', 'leaveType']);

            $annualType = LeaveType::where('code', LeaveBalanceService::CODE_ANNUAL)->first();

            // ensureBalance() lazily provisions the year row the same way consume()
            // would, so a first-leave-of-the-year employee sees their real quota
            // instead of a missing-row zero.
            $annualRemaining = $annualType
                ? $this->leaveBalanceService->ensureBalance($locked->employee, $annualType, $locked->start_date->year)->remaining_days
                : 0;

            $convertDays = min($annualRemaining, $locked->total_days);
            $alphaDays = $locked->total_days - $convertDays;

            // Reject the sick request first — the same status-flip + requestor
            // notification + balance release pair the old auto-expire used.
            $reason = 'No medical certificate within '.LeaveRequest::CERTIFICATE_DEADLINE_HOURS.' hours — resolved by HR.';
            $this->approvalWorkflowService->markApprovableAsRejected($locked, $reason);
            $this->leaveBalanceService->release($locked);

            $annualRequest = null;

            if ($convertDays > 0) {
                $annualRequest = LeaveRequest::create([
                    'employee_id' => $locked->employee_id,
                    'leave_type_id' => $annualType->id,
                    'start_date' => $locked->start_date,
                    'end_date' => $locked->start_date->copy()->addDays($convertDays - 1),
                    'total_days' => $convertDays,
                    'reason' => "Converted from sick leave #{$locked->id} (no medical certificate within 48h).",
                    'status' => LeaveRequest::STATUS_APPROVED,
                    'submitted_at' => now(),
                    'approved_at' => now(),
                    'converted_from_leave_request_id' => $locked->id,
                ]);

                // consume() re-validates remaining_days under lockForUpdate inside
                // this transaction — a concurrent annual approval since HR's preview
                // aborts the whole resolution instead of overdrawing the quota.
                $this->leaveBalanceService->consume($annualRequest);
            }

            $resolution = match (true) {
                $alphaDays === 0 => LeaveRequest::RESOLUTION_CONVERTED,
                $convertDays === 0 => LeaveRequest::RESOLUTION_ALPHA,
                default => LeaveRequest::RESOLUTION_PARTIAL_ALPHA,
            };

            $locked->update([
                'certificate_status' => LeaveRequest::CERT_STATUS_RESOLVED,
                'certificate_resolution' => $resolution,
                'certificate_resolved_at' => now(),
                'certificate_resolved_by' => $resolver->id,
            ]);

            Log::channel('audit')->info('Sick Leave overdue certificate resolved', [
                'leave_request_id' => $locked->id,
                'employee_id' => $locked->employee_id,
                'resolution' => $resolution,
                'converted_days' => $convertDays,
                'alpha_days' => $alphaDays,
                'annual_leave_request_id' => $annualRequest?->id,
                'resolved_by' => $resolver->id,
            ]);

            return [
                'resolution' => $resolution,
                'converted_days' => $convertDays,
                'alpha_days' => $alphaDays,
                'annual_leave_request_id' => $annualRequest?->id,
            ];
        });

        // Outside the transaction so a rollback can't leave ghost notifications.
        $this->notifyEmployeeResolved($leaveRequest->refresh(), $result);

        return $result;
    }

    protected function notifyEmployeeResolved(LeaveRequest $leaveRequest, array $result): void
    {
        try {
            $userId = $leaveRequest->employee?->user_id;

            if (! $userId) {
                return;
            }

            Notification::create([
                'user_id' => $userId,
                'type' => 'sick_leave_resolved',
                'title' => 'Sick Leave Resolved',
                'message' => "Your sick leave was resolved: {$result['converted_days']} day(s) converted to Annual Leave, {$result['alpha_days']} day(s) marked as Alpha (unpaid absence).",
                'data' => [
                    'leave_request_id' => $leaveRequest->id,
                    'resolution' => $result['resolution'],
                    'converted_days' => $result['converted_days'],
                    'alpha_days' => $result['alpha_days'],
                    'annual_leave_request_id' => $result['annual_leave_request_id'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send sick leave resolution notification: '.$e->getMessage(), [
                'leave_request_id' => $leaveRequest->id,
                'resolution' => $result['resolution'],
            ]);
        }
    }
}
