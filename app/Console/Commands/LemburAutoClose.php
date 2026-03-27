<?php

namespace App\Console\Commands;

use App\Models\Hris\OvertimeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LemburAutoClose extends Command
{
    protected $signature = 'lembur:auto-close
                            {--hours=6 : Sessions running longer than this many hours will be auto-closed}
                            {--cap=4   : Capped duration in hours assigned when auto-closing}
                            {--dry-run : Preview without saving}';

    protected $description = 'Auto-close overtime sessions that have been running longer than the threshold';

    public function handle(): int
    {
        $thresholdHours = (int) $this->option('hours');
        $capHours       = (int) $this->option('cap');
        $dryRun         = (bool) $this->option('dry-run');

        $cutoff = now()->subHours($thresholdHours);

        $stale = OvertimeLog::running()
            ->where('start_time', '<=', $cutoff)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale overtime sessions found.');
            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale session(s) (running > {$thresholdHours}h).");

        foreach ($stale as $log) {
            $endTime         = $log->start_time->addHours($capHours);
            $durationMinutes = $capHours * 60;

            $this->line(sprintf(
                '  [%d] user_id=%d task="%s" started=%s → capped end=%s (%dmin)',
                $log->id,
                $log->user_id,
                $log->task_name,
                $log->start_time->toDateTimeString(),
                $endTime->toDateTimeString(),
                $durationMinutes,
            ));

            if (! $dryRun) {
                $log->update([
                    'end_time'         => $endTime,
                    'duration_minutes' => $durationMinutes,
                    'status'           => OvertimeLog::STATUS_FLAGGED,
                    'auto_closed'      => true,
                    'rejection_note'   => "Auto-closed by system: session exceeded {$thresholdHours} hours. Duration capped at {$capHours} hours for HR review.",
                ]);

                Log::warning('LemburAutoClose: session auto-closed', [
                    'overtime_log_id' => $log->id,
                    'user_id'         => $log->user_id,
                    'started_at'      => $log->start_time->toIso8601String(),
                    'capped_end'      => $endTime->toIso8601String(),
                ]);
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — no changes saved.');
        } else {
            $this->info("Auto-closed {$stale->count()} session(s). Marked as 'flagged' for HR review.");
        }

        return self::SUCCESS;
    }
}
