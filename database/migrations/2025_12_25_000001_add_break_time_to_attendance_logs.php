<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->timestamp('break_start_time')->nullable()->after('check_out_photo_path');
            $table->timestamp('break_end_time')->nullable()->after('break_start_time');
            $table->integer('total_break_minutes')->nullable()->after('break_end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['break_start_time', 'break_end_time', 'total_break_minutes']);
        });
    }
};
