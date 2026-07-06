<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * UNPAID and SICK leave types have no quota, so LeaveBalanceService::consume()
     * intentionally drives remaining_days negative for them (see UnpaidLeaveTest).
     * The unsigned column let that pass on SQLite (tests) but throws an "out of
     * range value" error on MySQL (production) on the very first unpaid/sick
     * approval — this widens the column to match the already-intended behavior.
     */
    public function up(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->integer('remaining_days')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->unsignedInteger('remaining_days')->default(0)->change();
        });
    }
};
