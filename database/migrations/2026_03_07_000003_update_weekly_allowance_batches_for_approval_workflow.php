<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_allowance_batches', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
        });

        // Migrate existing rows to new status values before altering the enum
        DB::table('weekly_allowance_batches')
            ->whereIn('status', ['pending_hr', 'pending_final', 'approved'])
            ->update(['status' => 'calculated']);

        DB::table('weekly_allowance_batches')
            ->where('status', 'cancelled')
            ->update(['status' => 'draft']);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            // MySQL: alter the enum column to new values
            DB::statement("ALTER TABLE weekly_allowance_batches MODIFY COLUMN status ENUM('draft', 'calculated', 'pending_finance', 'pending_coo', 'rejected', 'paid') DEFAULT 'draft'");
        }
        // SQLite: enum columns are just text — no constraint enforcement, so skip alteration
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Revert new statuses back to safe legacy values
        DB::table('weekly_allowance_batches')
            ->whereIn('status', ['pending_coo', 'rejected'])
            ->update(['status' => 'pending_finance']);

        DB::table('weekly_allowance_batches')
            ->where('status', 'calculated')
            ->update(['status' => 'draft']);

        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE weekly_allowance_batches MODIFY COLUMN status ENUM('draft', 'pending_hr', 'pending_finance', 'pending_final', 'approved', 'paid', 'cancelled') DEFAULT 'draft'");
        }

        Schema::table('weekly_allowance_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
        });
    }
};
