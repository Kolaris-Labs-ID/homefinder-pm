<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->json('late_penalty_tiers')->nullable()->after('half_day_hours');
        });

        // Seed default tiers relative to work_start_time (09:00):
        // 09:40 = +40 min → 25%, 11:00 = +120 min → 50%, 13:00 = +240 min → 100%
        DB::table('attendance_settings')->update([
            'late_penalty_tiers' => json_encode([
                ['minutes_late' => 40,  'deduction_percent' => 25],
                ['minutes_late' => 120, 'deduction_percent' => 50],
                ['minutes_late' => 240, 'deduction_percent' => 100],
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('late_penalty_tiers');
        });
    }
};
