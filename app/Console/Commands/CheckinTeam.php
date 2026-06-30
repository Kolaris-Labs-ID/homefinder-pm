<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use Carbon\Carbon;
use Database\Seeders\DummyTeamAttendanceSeeder as Seed;
use Illuminate\Console\Command;

/**
 * Prod-safe alternative to DummyTeamAttendanceSeeder: marks EXISTING active
 * employees as checked-in today so the ESS Team tab shows them "On Duty".
 * Creates NO users/employees/positions — only tagged AttendanceLog rows — so it
 * never duplicates real people and is fully reversible via `dummy:teardown`.
 */
class CheckinTeam extends Command
{
    protected $signature = 'dummy:checkin-team
        {--limit= : Cap the number of employees to check in}
        {--dry-run : Report what would happen, write nothing}
        {--force : Skip confirmation}';

    protected $description = 'Check in existing active employees for today (tagged, reversible) to populate the ESS Team tab. Creates no new records.';

    public function handle(): int
    {
        $today = Carbon::today();
        $dry = (bool) $this->option('dry-run');

        $haveToday = AttendanceLog::whereDate('date', $today)->pluck('employee_id')->unique();

        $onLeave = LeaveRequest::where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('employee_id')
            ->unique();

        $query = Employee::active()
            ->whereNotIn('id', $haveToday->all())
            ->whereNotIn('id', $onLeave->all())
            ->orderBy('id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $eligible = $query->get();

        $this->table(['Metric', 'Count'], [
            ['Active employees', Employee::active()->count()],
            ['Already checked in / have attendance today', $haveToday->count()],
            ['On approved leave today (left as-is)', $onLeave->count()],
            [($dry ? '[DRY RUN] would check in' : 'Will check in'), $eligible->count()],
        ]);

        if ($eligible->isEmpty()) {
            $this->info('No eligible employees — nothing to do.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->warn('Dry run — nothing written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Check in {$eligible->count()} employees for {$today->toDateString()}?")) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($eligible->values() as $idx => $employee) {
            $checkIn = $today->copy()->setTime(7, 0)->addMinutes(($idx * 7) % 110);
            $status = $checkIn->lte($today->copy()->setTime(8, 15))
                ? AttendanceLog::STATUS_PRESENT
                : AttendanceLog::STATUS_LATE;

            AttendanceLog::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $today->toDateString()],
                [
                    'mode' => AttendanceLog::MODE_OFFICE,
                    'check_in_time' => $checkIn,
                    'check_out_time' => null,
                    'check_in_latitude' => -6.200000 + (($idx % 20) * 0.001),
                    'check_in_longitude' => 106.816666 + (($idx % 20) * 0.001),
                    'check_in_address' => 'Jakarta',
                    'source' => AttendanceLog::SOURCE_MOBILE,
                    'status' => $status,
                    'missing_checkout' => false,
                    'metadata' => ['seed_batch' => Seed::BATCH],
                ]
            );
            $created++;
        }

        $this->info("Checked in {$created} employees for {$today->toDateString()}. Roll back with: php artisan dummy:teardown");

        return self::SUCCESS;
    }
}
