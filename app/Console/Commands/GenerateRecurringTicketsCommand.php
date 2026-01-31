<?php

namespace App\Console\Commands;

use App\Services\RecurringTicketService;
use Illuminate\Console\Command;

class GenerateRecurringTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:generate-recurring
                            {--dry-run : Show what would be generated without creating tickets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tickets from active recurring templates';

    /**
     * Execute the console command.
     */
    public function handle(RecurringTicketService $service): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No tickets will be created');
            $this->newLine();
        }

        $this->info('Processing recurring ticket templates...');
        $this->newLine();

        if ($dryRun) {
            return $this->handleDryRun($service);
        }

        return $this->handleGeneration($service);
    }

    /**
     * Handle dry run mode - show what would be generated.
     */
    protected function handleDryRun(RecurringTicketService $service): int
    {
        $templates = \App\Models\TicketTemplate::active()
            ->with(['project', 'assignees', 'initialStatus', 'priority'])
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('No active templates found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$templates->count()} active template(s)");
        $this->newLine();

        $wouldGenerate = 0;
        $wouldSkip = 0;

        foreach ($templates as $template) {
            $shouldGenerate = $service->shouldGenerateToday($template);
            $targetDate = $service->calculateNextTargetDate(
                $template->frequency,
                $template->day_value
            );

            if ($shouldGenerate) {
                // Check if already generated
                if ($template->hasGeneratedForPeriod($targetDate)) {
                    $this->line("  ⏭️  [{$template->id}] {$template->name}");
                    $this->line("      → Would skip (already generated for {$targetDate->toDateString()})");
                    $wouldSkip++;
                } else {
                    $statusName = $template->initialStatus?->name ?? 'Fallback';
                    $this->line("  ✅ [{$template->id}] {$template->name}");
                    $this->line("      → Would generate ticket for {$targetDate->toDateString()}");
                    $this->line("      → Project: {$template->project->name}");
                    $this->line("      → Status: {$statusName}");
                    $this->line("      → Assignees: {$template->assignees->count()}");
                    $wouldGenerate++;
                }
            } else {
                $this->line("  ⏭️  [{$template->id}] {$template->name}");
                $this->line("      → Would skip (not due today, next: {$targetDate->toDateString()})");
                $wouldSkip++;
            }
            $this->newLine();
        }

        $this->newLine();
        $this->info("📊 Summary (DRY RUN):");
        $this->line("   Would generate: {$wouldGenerate} ticket(s)");
        $this->line("   Would skip: {$wouldSkip} template(s)");

        return Command::SUCCESS;
    }

    /**
     * Handle actual ticket generation.
     */
    protected function handleGeneration(RecurringTicketService $service): int
    {
        $summary = $service->processAllTemplates();

        // Output progress details
        foreach ($summary['details'] as $detail) {
            $templateId = $detail['template_id'];

            switch ($detail['status']) {
                case 'generated':
                    $this->line("  ✅ [{$templateId}] Generated ticket #{$detail['ticket_id']} (due: {$detail['target_date']})");
                    break;
                case 'skipped':
                    $reason = $detail['reason'] === 'already_generated'
                        ? "already generated for {$detail['target_date']}"
                        : 'not due today';
                    $this->line("  ⏭️  [{$templateId}] Skipped ({$reason})");
                    break;
                case 'error':
                    $this->error("  ❌ [{$templateId}] Error: {$detail['error']}");
                    break;
            }
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("   Processed: {$summary['processed']} template(s)");
        $this->line("   Generated: {$summary['generated']} ticket(s)");
        $this->line("   Skipped: {$summary['skipped']} template(s)");

        if ($summary['errors'] > 0) {
            $this->error("   Errors: {$summary['errors']}");
            return Command::FAILURE;
        }

        $this->info("✅ Completed successfully!");

        return Command::SUCCESS;
    }
}
