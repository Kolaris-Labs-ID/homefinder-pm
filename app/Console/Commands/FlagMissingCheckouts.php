<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FlagMissingCheckouts extends Command
{
    protected $signature = 'attendance:flag-missing-checkouts {--date= : Date to check (Y-m-d), defaults to today}';

    protected $description = 'Flag attendance records that have check-in but no check-out';

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today('Asia/Jakarta');

        $flagged = AttendanceLog::whereDate('date', $date)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->where('missing_checkout', false)
            ->update(['missing_checkout' => true]);

        $this->info("Flagged {$flagged} record(s) with missing checkout for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
