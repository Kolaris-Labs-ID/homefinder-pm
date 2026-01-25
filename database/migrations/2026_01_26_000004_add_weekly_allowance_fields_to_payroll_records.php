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
        Schema::table('payroll_records', function (Blueprint $table) {
            // Weekly allowance reconciliation fields
            $table->decimal('weekly_allowance_earned', 15, 2)->nullable()->after('daily_allowance_total');
            $table->decimal('weekly_allowance_paid', 15, 2)->nullable()->after('weekly_allowance_earned');
            $table->decimal('weekly_allowance_adjustment', 15, 2)->nullable()->after('weekly_allowance_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_allowance_earned',
                'weekly_allowance_paid',
                'weekly_allowance_adjustment',
            ]);
        });
    }
};
