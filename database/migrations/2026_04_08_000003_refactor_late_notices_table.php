<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('late_notices', function (Blueprint $table) {
            // Link to the triggering attendance log
            $table->foreignId('attendance_log_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('attendance_logs')
                ->nullOnDelete();

            // Snapshot of how late the employee was (set at auto-creation)
            $table->unsignedInteger('minutes_late')->nullable()->after('date');
            $table->unsignedTinyInteger('penalty_percent')->nullable()->after('minutes_late');

            // HR fills the reason after discussing with the employee
            $table->text('reason')->nullable()->change();

            // estimated_arrival_time is no longer relevant (system auto-creates)
            $table->time('estimated_arrival_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('late_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_log_id');
            $table->dropColumn(['minutes_late', 'penalty_percent']);
            $table->text('reason')->nullable(false)->change();
            $table->time('estimated_arrival_time')->nullable(false)->change();
        });
    }
};
