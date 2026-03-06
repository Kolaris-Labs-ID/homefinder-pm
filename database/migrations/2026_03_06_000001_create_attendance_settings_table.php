<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('work_start_time')->default('09:00');
            $table->integer('late_grace_period_minutes')->default(15);
            $table->integer('wfh_quota_per_week')->default(1);
            $table->decimal('full_day_hours', 4, 2)->default(8.00);
            $table->decimal('half_day_hours', 4, 2)->default(4.00);
            $table->timestamps();
        });

        // Seed the single default row
        DB::table('attendance_settings')->insert([
            'work_start_time' => '09:00',
            'late_grace_period_minutes' => 15,
            'wfh_quota_per_week' => 1,
            'full_day_hours' => 8.00,
            'half_day_hours' => 4.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
