<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing MySQL installs may still use ENUM('leave','reimbursement','payroll_adjustment').
     * Widen to VARCHAR so MODULE_TRAINING ('training') and future modules are valid.
     */
    public function up(): void
    {
        if (! Schema::hasTable('approval_workflows')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE approval_workflows MODIFY for_module VARCHAR(32) NOT NULL');
    }

    public function down(): void
    {
        // Reverting ENUM width is lossy; leave schema as VARCHAR on rollback.
    }
};
