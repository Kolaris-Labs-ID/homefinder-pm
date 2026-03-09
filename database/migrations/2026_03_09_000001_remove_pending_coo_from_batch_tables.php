<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove 'pending_coo' status from payrun_batches and weekly_allowance_batches.
     * Also clean up any COO workflow step rows.
     */
    public function up(): void
    {
        // Move any existing pending_coo batches to pending_finance
        DB::table('payrun_batches')
            ->where('status', 'pending_coo')
            ->update(['status' => 'pending_finance']);

        DB::table('weekly_allowance_batches')
            ->where('status', 'pending_coo')
            ->update(['status' => 'pending_finance']);

        // Alter enums to remove pending_coo (MySQL only — SQLite uses TEXT, no enum constraint)
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrun_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'rejected', 'paid') DEFAULT 'draft'");
            DB::statement("ALTER TABLE weekly_allowance_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'rejected', 'paid') DEFAULT 'draft'");
        }

        // Remove any COO step rows from payroll_workflow_steps
        DB::table('payroll_workflow_steps')
            ->where('step_type', 'coo')
            ->delete();
    }

    /**
     * Reverse the migration — add pending_coo back to enums.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrun_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'pending_coo', 'rejected', 'paid') DEFAULT 'draft'");
            DB::statement("ALTER TABLE weekly_allowance_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'pending_coo', 'rejected', 'paid') DEFAULT 'draft'");
        }
    }
};
