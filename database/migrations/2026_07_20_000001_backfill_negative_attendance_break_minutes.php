<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recalculate break durations corrupted by comparing a Jakarta wall-clock
 * timestamp with the same wall-clock value interpreted as UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendance_logs')
            ->select(['id', 'break_start_time', 'break_end_time'])
            ->whereNotNull('break_start_time')
            ->whereNotNull('break_end_time')
            ->where('total_break_minutes', '<', 0)
            ->chunkById(500, function ($attendanceLogs): void {
                foreach ($attendanceLogs as $attendanceLog) {
                    $breakStart = CarbonImmutable::createFromFormat(
                        'Y-m-d H:i:s',
                        $attendanceLog->break_start_time,
                        'Asia/Jakarta'
                    );
                    $breakEnd = CarbonImmutable::createFromFormat(
                        'Y-m-d H:i:s',
                        $attendanceLog->break_end_time,
                        'Asia/Jakarta'
                    );
                    $totalBreakMinutes = $breakStart->diffInMinutes($breakEnd);

                    if ($totalBreakMinutes < 0) {
                        continue;
                    }

                    DB::table('attendance_logs')
                        ->where('id', $attendanceLog->id)
                        ->where('total_break_minutes', '<', 0)
                        ->update(['total_break_minutes' => (int) $totalBreakMinutes]);
                }
            });
    }

    public function down(): void
    {
        // Corrected durations cannot be safely restored to their corrupt values.
    }
};
