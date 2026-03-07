<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrun_batches', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        // Migrate any existing 'approved' rows to 'calculated' before changing the enum
        DB::table('payrun_batches')->where('status', 'approved')->update(['status' => 'calculated']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            // MySQL: alter the enum column to new values
            DB::statement("ALTER TABLE payrun_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'pending_coo', 'rejected', 'paid') DEFAULT 'draft'");
        }
        // SQLite: enum columns are just text — no constraint enforcement, so skip alteration
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Revert status changes: move pending/rejected back to calculated
        DB::table('payrun_batches')
            ->whereIn('status', ['pending_finance', 'pending_coo', 'rejected'])
            ->update(['status' => 'calculated']);

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE payrun_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'approved', 'paid') DEFAULT 'draft'");
        }

        Schema::table('payrun_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['submitted_at', 'rejection_reason']);
        });
    }
};
