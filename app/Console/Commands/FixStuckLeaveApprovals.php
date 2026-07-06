<?php

namespace App\Console\Commands;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\LeaveRequest;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStuckLeaveApprovals extends Command
{
    protected $signature = 'hris:fix-stuck-leave-approvals {--dry-run : Report what would change without writing}';

    protected $description = 'Finalize leave requests left "pending" despite every approval step being resolved (legacy data predating fix 84c5b49)';

    public function handle(ApprovalWorkflowService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stuck = LeaveRequest::where('status', LeaveRequest::STATUS_PENDING)
            ->whereDoesntHave('approvalSteps', fn ($q) => $q->where('status', ApprovalStep::STATUS_PENDING))
            ->whereHas('approvalSteps', fn ($q) => $q->where('status', ApprovalStep::STATUS_APPROVED))
            ->get();

        $this->info("Stuck leave requests found: {$stuck->count()}");

        foreach ($stuck as $leaveRequest) {
            $this->line("  - #{$leaveRequest->id} employee #{$leaveRequest->employee_id}");
        }

        if ($dryRun) {
            $this->comment('Dry run — no changes written.');

            return self::SUCCESS;
        }

        foreach ($stuck as $leaveRequest) {
            try {
                DB::transaction(fn () => $service->markApprovableAsApproved($leaveRequest->fresh()));
                $this->info("Fixed #{$leaveRequest->id}");
            } catch (\Throwable $e) {
                $this->error("#{$leaveRequest->id} still fails: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
