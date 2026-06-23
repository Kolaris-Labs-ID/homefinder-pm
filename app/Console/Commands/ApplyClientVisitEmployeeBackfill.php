<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyClientVisitEmployeeBackfill extends Command
{
    protected $signature = 'client-visits:apply-employee-backfill
        {--apply : Write the corrections (default is dry-run)}
        {--include-activity : Also correct visits that have check-in/attendance activity (NOT recommended)}
        {--id=* : Restrict to specific client_visit ids}
        {--revert : Restore old_employee_id for rows already applied}';

    protected $description = 'Apply reviewed client_visit employee_id corrections recorded by the backfill migration';

    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('client_visit_employee_backfill')) {
            $this->error('Review table missing. Run the backfill migration first.');
            return self::FAILURE;
        }

        $ids = $this->option('id');
        $apply = $this->option('apply');

        if ($this->option('revert')) {
            return $this->revert($ids);
        }

        $query = DB::table('client_visit_employee_backfill')->where('applied', false);
        if (! $this->option('include-activity')) {
            $query->where('has_activity', false);
        }
        if ($ids) {
            $query->whereIn('client_visit_id', $ids);
        }

        $candidates = $query->orderBy('client_visit_id')->get();

        if ($candidates->isEmpty()) {
            $this->info('No pending candidates match. Nothing to do.');
            return self::SUCCESS;
        }

        $this->table(
            ['visit', 'old_emp', 'old_name', '→ new_emp', 'new_name', 'activity'],
            $candidates->map(fn ($c) => [
                $c->client_visit_id, $c->old_employee_id, $c->old_employee_name,
                $c->suggested_employee_id, $c->suggested_employee_name, $c->has_activity ? 'YES' : '',
            ])
        );

        if (! $apply) {
            $this->warn("Dry-run: {$candidates->count()} row(s) would be corrected. Re-run with --apply to write.");
            return self::SUCCESS;
        }

        $count = 0;
        DB::transaction(function () use ($candidates, &$count) {
            foreach ($candidates as $c) {
                DB::table('client_visits')
                    ->where('id', $c->client_visit_id)
                    ->update(['employee_id' => $c->suggested_employee_id]);
                DB::table('client_visit_employee_backfill')
                    ->where('id', $c->id)
                    ->update(['applied' => true, 'updated_at' => now()]);
                $count++;
            }
        });

        $this->info("Applied {$count} correction(s).");
        return self::SUCCESS;
    }

    private function revert(array $ids): int
    {
        $query = DB::table('client_visit_employee_backfill')->where('applied', true);
        if ($ids) {
            $query->whereIn('client_visit_id', $ids);
        }
        $rows = $query->get();

        $count = 0;
        DB::transaction(function () use ($rows, &$count) {
            foreach ($rows as $c) {
                DB::table('client_visits')
                    ->where('id', $c->client_visit_id)
                    ->update(['employee_id' => $c->old_employee_id]);
                DB::table('client_visit_employee_backfill')
                    ->where('id', $c->id)
                    ->update(['applied' => false, 'updated_at' => now()]);
                $count++;
            }
        });

        $this->info("Reverted {$count} correction(s).");
        return self::SUCCESS;
    }
}
