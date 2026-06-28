<?php

namespace App\Console\Commands;

use App\Models\Hris\ApprovalStep;
use Illuminate\Console\Command;

class PruneOrphanApprovalSteps extends Command
{
    protected $signature = 'hris:prune-orphan-approval-steps {--delete : Delete the orphaned steps (default is a dry-run)}';

    protected $description = 'Find (and optionally delete) approval_steps whose approvable record no longer exists';

    public function handle(): int
    {
        $orphans = ApprovalStep::whereDoesntHaveMorph('approvable', '*')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned approval steps. Nothing to prune.');

            return self::SUCCESS;
        }

        $this->warn("Found {$orphans->count()} orphaned approval step(s):");
        foreach ($orphans as $step) {
            $this->line("  [{$step->id}] {$step->approvable_type}#{$step->approvable_id} status={$step->status}");
        }

        if (! $this->option('delete')) {
            $this->newLine();
            $this->info('Dry-run. Re-run with --delete to remove them.');

            return self::SUCCESS;
        }

        $count = ApprovalStep::whereKey($orphans->pluck('id'))->delete();
        $this->newLine();
        $this->info("Deleted {$count} orphaned approval step(s).");

        return self::SUCCESS;
    }
}
