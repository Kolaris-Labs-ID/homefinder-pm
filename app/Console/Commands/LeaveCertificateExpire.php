<?php

namespace App\Console\Commands;

use App\Models\Hris\LeaveRequest;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCertificateExpire extends Command
{
    protected $signature = 'hris:leave-certificate-expire';

    protected $description = 'Flag provisionally-approved Sick Leave requests past the 48h medical certificate deadline as overdue, awaiting HR resolution (convert to Annual Leave or Alpha)';

    public function handle(): int
    {
        $overdue = LeaveRequest::certificateOverdue()->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue certificate deadlines found.');

            return self::SUCCESS;
        }

        $this->info("Found {$overdue->count()} overdue certificate deadline(s).");
        $flagged = 0;

        foreach ($overdue as $leaveRequest) {
            try {
                // Row-lock and re-check inside the transaction: the deadline query above
                // ran outside any lock, so a concurrent upload or a second worker running
                // this same command could have already resolved this record by the time
                // we get here. Re-checking under lockForUpdate() makes this safe to run
                // from multiple workers and safe to re-run without double-processing.
                $didFlag = DB::transaction(function () use ($leaveRequest): bool {
                    $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->first();

                    if (! $locked
                        || $locked->status !== LeaveRequest::STATUS_APPROVED
                        || ! in_array($locked->certificate_status, [
                            LeaveRequest::CERT_STATUS_PENDING,
                            LeaveRequest::CERT_STATUS_REJECTED,
                        ], true)
                        || $locked->certificate_deadline_at === null
                        || $locked->certificate_deadline_at->isAfter(now())
                    ) {
                        return false;
                    }

                    // No auto-rejection: the leave stays approved until HR resolves it
                    // (convert to Annual Leave or mark as Alpha) — see
                    // SickLeaveResolutionService.
                    $locked->update([
                        'certificate_status' => LeaveRequest::CERT_STATUS_OVERDUE,
                    ]);

                    Log::channel('audit')->info('Sick Leave certificate overdue: flagged for HR resolution', [
                        'leave_request_id' => $locked->id,
                        'employee_id' => $locked->employee_id,
                        'certificate_status' => LeaveRequest::CERT_STATUS_OVERDUE,
                        'certificate_deadline_at' => $locked->certificate_deadline_at->toIso8601String(),
                        'flagged_at' => now()->toIso8601String(),
                        'actor' => 'System',
                    ]);

                    return true;
                });

                if ($didFlag) {
                    // Outside the transaction so a rollback can't leave ghost notifications.
                    $this->notifyHROverdue($leaveRequest->fresh());
                    $flagged++;
                    $this->line("  ✓ Flagged leave request #{$leaveRequest->id} as overdue (employee_id={$leaveRequest->employee_id})");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to flag leave request #{$leaveRequest->id}: ".$e->getMessage());
                Log::error('LeaveCertificateExpire: failed to flag overdue leave request', [
                    'leave_request_id' => $leaveRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Flagged {$flagged} leave request(s) for HR resolution.");

        return self::SUCCESS;
    }

    /**
     * Same HR-role lookup convention as LeaveRequestController::notifyHRCertificateSubmitted().
     */
    protected function notifyHROverdue(LeaveRequest $leaveRequest): void
    {
        $employeeName = $leaveRequest->employee->user?->name ?? 'Unknown';

        $hrUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['hr-admin', 'hr-manager']);
        })->get();

        foreach ($hrUsers as $hrUser) {
            \App\Models\Notification::create([
                'user_id' => $hrUser->id,
                'type' => 'certificate_overdue_hr',
                'title' => 'Sick Leave Needs Resolution',
                'message' => "{$employeeName}'s sick leave passed the 48h certificate deadline — resolve it (convert to Annual Leave or Alpha).",
                'data' => [
                    'leave_request_id' => $leaveRequest->id,
                    'employee_id' => $leaveRequest->employee_id,
                    'employee_name' => $employeeName,
                ],
            ]);
        }
    }
}
