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
        Schema::table('payrun_batches', function (Blueprint $table) {
            $table->unsignedInteger('current_finance_step_order')->nullable()->after('status');
        });

        Schema::table('weekly_allowance_batches', function (Blueprint $table) {
            $table->unsignedInteger('current_finance_step_order')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrun_batches', function (Blueprint $table) {
            $table->dropColumn('current_finance_step_order');
        });

        Schema::table('weekly_allowance_batches', function (Blueprint $table) {
            $table->dropColumn('current_finance_step_order');
        });
    }
};
