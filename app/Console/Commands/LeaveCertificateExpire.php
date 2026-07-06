<?php

namespace App\Console\Commands;

use App\Models\Hris\LeaveRequest;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCertificateExpire extends Command
{
    protected $signature = 'hris:leave-certificate-expire';

    protected $description = 'Auto-reject provisionally-approved Sick Leave requests whose 48h medical certificate deadline has passed without a submission';

    public function handle(ApprovalWorkflowService $approvalWorkflowService, LeaveBalanceService $leaveBalanceService): int
    {
        $overdue = LeaveRequest::certificateOverdue()->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue certificate deadlines found.');

            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} overdue certificate deadline(s).");
        $converted = 0;

        foreach ($overdue as $leaveRequest) {
            try {
                // Row-lock and re-check inside the transaction: the deadline query above
                // ran outside any lock, so a concurrent upload or a second worker running
                // this same command could have already resolved this record by the time
                // we get here. Re-checking under lockForUpdate() makes this safe to run
                // from multiple workers and safe to re-run without double-processing.
                DB::transaction(function () use ($leaveRequest, $approvalWorkflowService, $leaveBalanceService) {
                    $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->first();

                    if (! $locked
                        || $locked->status !== LeaveRequest::STATUS_APPROVED
                        || $locked->certificate_status !== LeaveRequest::CERT_STATUS_PENDING
                        || $locked->auto_rejected
                        || $locked->certificate_deadline_at === null
                        || $locked->certificate_deadline_at->isAfter(now())
                    ) {
                        return;
                    }

                    $reason = 'Missing medical certificate after '.LeaveRequest::CERTIFICATE_DEADLINE_HOURS.' hours.';

                    // Reuse the same status-flip + requestor notification every other
                    // rejection in the system goes through — do not duplicate it here.
                    $approvalWorkflowService->markApprovableAsRejected($locked, $reason);

                    $leaveBalanceService->release($locked);

                    $locked->update([
                        'certificate_status' => LeaveRequest::CERT_STATUS_EXPIRED,
                        'auto_rejected' => true,
                    ]);

                    Log::channel('audit')->info('Sick Leave auto-rejected: medical certificate deadline passed', [
                        'leave_request_id' => $locked->id,
                        'employee_id' => $locked->employee_id,
                        'previous_status' => LeaveRequest::STATUS_APPROVED,
                        'new_status' => LeaveRequest::STATUS_REJECTED,
                        'certificate_status' => LeaveRequest::CERT_STATUS_EXPIRED,
                        'certificate_deadline_at' => $locked->certificate_deadline_at->toIso8601String(),
                        'converted_at' => now()->toIso8601String(),
                        'actor' => 'System',
                        'reason' => $reason,
                    ]);
                });

                $converted++;
                $this->line("  ✓ Auto-rejected leave request #{$leaveRequest->id} (employee_id={$leaveRequest->employee_id})");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to auto-reject leave request #{$leaveRequest->id}: ".$e->getMessage());
                Log::error('LeaveCertificateExpire: failed to auto-reject leave request', [
                    'leave_request_id' => $leaveRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Auto-rejected {$converted} leave request(s).");

        return self::SUCCESS;
    }
}
