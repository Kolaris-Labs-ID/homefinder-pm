<?php

namespace App\Console\Commands;

use App\Models\Hris\AttendanceLog;
use App\Services\Hris\PayrollPeriodService;
use Illuminate\Console\Command;

class FixImportedAttendanceDates extends Command
{
    protected $signature = 'attendance:fix-imported-year {--confirm : Actually apply the changes (dry-run by default)}';

    protected $description = 'Fix imported attendance logs that have incorrect year 2026 → 2025';

    public function handle(): int
    {
        $isDryRun = !$this->option('confirm');

        $affectedLogs = AttendanceLog::where('source', AttendanceLog::SOURCE_IMPORT)
            ->whereYear('date', 2026)
            ->orderBy('date')
            ->get();

        if ($affectedLogs->isEmpty()) {
            $this->info('No imported attendance logs with year 2026 found. Nothing to fix.');
            return self::SUCCESS;
        }

        $this->info(($isDryRun ? '[DRY RUN] ' : '') . "Found {$affectedLogs->count()} imported attendance log(s) with year 2026.");
        $this->newLine();

        // Preview table
        $headers = ['ID', 'Employee', 'Date (current)', 'Date (fixed)', 'Check-In', 'Check-Out'];
        $rows = $affectedLogs->map(function (AttendanceLog $log) {
            $fixedDate = $log->date->copy()->subYear();
            return [
                $log->id,
                $log->employee?->user?->name ?? "employee_id:{$log->employee_id}",
                $log->date->format('Y-m-d'),
                $fixedDate->format('Y-m-d'),
                $log->check_in_time?->format('Y-m-d H:i') ?? '-',
                $log->check_out_time?->format('Y-m-d H:i') ?? '-',
            ];
        })->toArray();

        $this->table($headers, $rows);

        if ($isDryRun) {
            $this->warn('This is a dry run. Run with --confirm to apply changes.');
            return self::SUCCESS;
        }

        $payrollPeriodService = app(PayrollPeriodService::class);
        $updatedCount = 0;

        foreach ($affectedLogs as $log) {
            $newDate = $log->date->copy()->subYear();
            $newCheckIn = $log->check_in_time?->copy()->subYear();
            $newCheckOut = $log->check_out_time?->copy()->subYear();

            $updateData = [
                'date' => $newDate,
                'check_in_time' => $newCheckIn,
                'check_out_time' => $newCheckOut,
            ];

            // Recalculate payroll period
            $period = $payrollPeriodService->getPeriodForDate($newDate);
            $updateData['payroll_period_name'] = $period['name'];
            $updateData['payroll_period_start'] = $period['start_date'];
            $updateData['payroll_period_end'] = $period['end_date'];

            $log->update($updateData);
            $updatedCount++;
        }

        $this->info("Successfully updated {$updatedCount} attendance log(s) from 2026 to 2025.");

        return self::SUCCESS;
    }
}
