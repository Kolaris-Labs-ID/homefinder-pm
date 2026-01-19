<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds payroll period tracking fields to attendance_logs table.
     * The payroll period follows a 26th-to-25th structure:
     * - Period Start: 26th of the month
     * - Period End: 25th of the next month
     * - Period Name: Named after the ending month (e.g., "February 2025" for Jan 26 - Feb 25)
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Payroll period name in format "MonthName YYYY" (e.g., "February 2025")
            $table->string('payroll_period_name', 50)->nullable()->after('status');

            // Period start date (26th of previous month)
            $table->date('payroll_period_start')->nullable()->after('payroll_period_name');

            // Period end date (25th of period month)
            $table->date('payroll_period_end')->nullable()->after('payroll_period_start');

            // Index for efficient period queries by name
            $table->index('payroll_period_name', 'attendance_logs_payroll_period_name_index');

            // Composite index for date range queries
            $table->index(['payroll_period_start', 'payroll_period_end'], 'attendance_logs_payroll_period_dates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('attendance_logs_payroll_period_name_index');
            $table->dropIndex('attendance_logs_payroll_period_dates_index');

            // Drop columns
            $table->dropColumn([
                'payroll_period_name',
                'payroll_period_start',
                'payroll_period_end',
            ]);
        });
    }
};
