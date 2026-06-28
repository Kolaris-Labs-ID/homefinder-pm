<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\LateNotice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWeekendLate extends Command
{
    protected $signature = 'attendance:fix-weekend-late
                            {--from= : Start date (Y-m-d) to scope the cleanup}
                            {--to= : End date (Y-m-d) to scope the cleanup}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Re-status weekend late attendance logs to present and delete their spurious pending late notices';

    public function handle(): int
    {
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;
        $dryRun = (bool) $this->option('dry-run');

        $scope = function ($query) use ($from, $to) {
            if ($from) {
                $query->whereDate('date', '>=', $from);
            }
            if ($to) {
                $query->whereDate('date', '<=', $to);
            }

            return $query;
        };

        // Weekend detection runs in PHP for SQLite (tests) / MySQL (prod) portability.
        // ponytail: loads the 'late' subset into memory; fine for a scoped one-off cleanup.
        $isWeekend = fn ($row) => Carbon::parse($row->date)->isWeekend();

        $lateLogs = $scope(AttendanceLog::where('status', AttendanceLog::STATUS_LATE))
            ->get()->filter($isWeekend);

        $pendingNotices = $scope(LateNotice::where('status', LateNotice::STATUS_PENDING))
            ->get()->filter($isWeekend);

        $reviewedNotices = $scope(
            LateNotice::whereIn('status', [LateNotice::STATUS_APPROVED, LateNotice::STATUS_REJECTED])
        )->get()->filter($isWeekend);

        $this->info("Weekend late logs to re-status → present: {$lateLogs->count()}");
        $this->info("Pending weekend late notices to delete: {$pendingNotices->count()}");

        if ($reviewedNotices->isNotEmpty()) {
            $this->warn("{$reviewedNotices->count()} approved/rejected weekend notice(s) left untouched for manual HR review:");
            foreach ($reviewedNotices as $n) {
                $this->warn("  - notice #{$n->id} employee #{$n->employee_id} {$n->date->toDateString()} ({$n->status})");
            }
        }

        if ($dryRun) {
            $this->comment('Dry run — no changes written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($lateLogs, $pendingNotices) {
            AttendanceLog::whereIn('id', $lateLogs->pluck('id'))
                ->update(['status' => AttendanceLog::STATUS_PRESENT]);

            LateNotice::whereIn('id', $pendingNotices->pluck('id'))->delete();
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}
