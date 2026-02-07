<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

class BackfillProjectOwners extends Command
{
    protected $signature = 'projects:backfill-owners';

    protected $description = 'Set created_by for existing projects using the first member added';

    public function handle(): int
    {
        $projects = Project::whereNull('created_by')->get();

        if ($projects->isEmpty()) {
            $this->info('All projects already have an owner. Nothing to backfill.');
            return self::SUCCESS;
        }

        $this->info("Found {$projects->count()} projects without an owner.");

        $updated = 0;
        $skipped = 0;

        foreach ($projects as $project) {
            $firstMember = $project->members()->orderBy('project_members.created_at')->first();

            if ($firstMember) {
                $project->update(['created_by' => $firstMember->id]);
                $this->line("  ✓ [{$project->id}] {$project->name} → {$firstMember->name}");
                $updated++;
            } else {
                $this->warn("  ✗ [{$project->id}] {$project->name} → No members found, skipped.");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}
