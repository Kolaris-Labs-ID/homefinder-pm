<?php

namespace App\Console\Commands;

use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\TrainingRecord;
use App\Services\Hris\ApprovalWorkflowService;
use Illuminate\Console\Command;

class BackfillTrainingApprovals extends Command
{
    protected $signature = 'hris:backfill-training-approvals';

    protected $description = 'Route training records that were submitted before a training workflow existed into the approval console';

    public function handle(ApprovalWorkflowService $workflows): int
    {
        if (! $workflows->getActiveWorkflowForModule(ApprovalWorkflow::MODULE_TRAINING)) {
            $this->error('No active "training" approval workflow. Run: php artisan db:seed --class=TrainingApprovalWorkflowSeeder');

            return self::FAILURE;
        }

        $orphans = TrainingRecord::doesntHave('approvalSteps')->get();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned training records. Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("Found {$orphans->count()} training record(s) with no approval steps.");

        foreach ($orphans as $record) {
            $workflows->initializeWorkflow($record, ApprovalWorkflow::MODULE_TRAINING);
            $this->line("  ✓ [{$record->id}] {$record->training_name} → routed to approval");
        }

        $this->newLine();
        $this->info("Done. Routed {$orphans->count()} record(s) to the approval console.");

        return self::SUCCESS;
    }
}
