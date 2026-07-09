<?php

namespace App\Console\Commands;

use App\Models\Hris\LeaveRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LeaveCertificateRemind extends Command
{
    protected $signature = 'hris:leave-certificate-remind
                            {--hours=24 : Send a reminder when the certificate deadline is within this many hours}';

    protected $description = 'Remind employees (and HR) about Sick Leave requests whose medical certificate deadline is approaching';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $approaching = LeaveRequest::certificateDeadlineWithinHours($hours)->get();

        if ($approaching->isEmpty()) {
            $this->info('No approaching certificate deadlines found.');

            return self::SUCCESS;
        }

        $this->info("Found {$approaching->count()} approaching certificate deadline(s).");
        $hrUsers = $this->getHRUsers();
        $notified = 0;

        foreach ($approaching as $leaveRequest) {
            try {
                $hoursRemaining = (int) ceil(now()->diffInHours($leaveRequest->certificate_deadline_at, false));

                $this->notifyEmployee($leaveRequest, $hoursRemaining);
                $notified++;

                foreach ($hrUsers as $hrUser) {
                    $this->notifyHR($leaveRequest, $hrUser, $hoursRemaining);
                    $notified++;
                }

                $this->line("  ✓ Reminded leave request #{$leaveRequest->id} ({$hoursRemaining}h remaining)");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to remind for leave request {$leaveRequest->id}: ".$e->getMessage());
                Log::error('Failed to send certificate reminder notification', [
                    'leave_request_id' => $leaveRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Sent {$notified} notification(s).");

        return self::SUCCESS;
    }

    private function notifyEmployee(LeaveRequest $leaveRequest, int $hoursRemaining): void
    {
        $employee = $leaveRequest->employee;

        if (! $employee || ! $employee->user_id) {
            Log::warning('Cannot remind employee - no user linked', [
                'leave_request_id' => $leaveRequest->id,
                'employee_id' => $employee?->id,
            ]);

            return;
        }

        Notification::create([
            'user_id' => $employee->user_id,
            'type' => 'certificate_reminder',
            'title' => 'Medical Certificate Needed',
            'message' => "Please upload your medical certificate for your Sick Leave — {$hoursRemaining} hour(s) remaining before it's escalated to HR for resolution (conversion to Annual Leave or unpaid absence).",
            'data' => [
                'leave_request_id' => $leaveRequest->id,
                'certificate_deadline_at' => $leaveRequest->certificate_deadline_at->toIso8601String(),
                'hours_remaining' => $hoursRemaining,
                'actor_id' => null,
                'actor_name' => 'System',
            ],
        ]);
    }

    private function notifyHR(LeaveRequest $leaveRequest, User $hrUser, int $hoursRemaining): void
    {
        $employeeName = $leaveRequest->employee->user?->name ?? 'Unknown';

        Notification::create([
            'user_id' => $hrUser->id,
            'type' => 'certificate_reminder_hr',
            'title' => 'Employee Certificate Pending',
            'message' => "{$employeeName}'s Sick Leave medical certificate is due in {$hoursRemaining} hour(s).",
            'data' => [
                'leave_request_id' => $leaveRequest->id,
                'employee_id' => $leaveRequest->employee_id,
                'employee_name' => $employeeName,
                'certificate_deadline_at' => $leaveRequest->certificate_deadline_at->toIso8601String(),
                'hours_remaining' => $hoursRemaining,
                'actor_id' => null,
                'actor_name' => 'System',
            ],
        ]);
    }

    /**
     * Same HR-role lookup as CheckExpiringCertifications::getHRUsers().
     */
    private function getHRUsers(): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['hr-admin', 'hr-manager']);
        })->get();
    }
}
