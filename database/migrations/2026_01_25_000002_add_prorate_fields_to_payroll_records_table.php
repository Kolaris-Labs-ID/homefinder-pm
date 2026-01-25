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
            // Prorate tracking
            $table->integer('total_working_days')->nullable()->after('details');
            $table->integer('actual_days_worked')->nullable()->after('total_working_days');
            $table->decimal('prorate_ratio', 5, 4)->nullable()->after('actual_days_worked');

            // Salary breakdown
            $table->decimal('full_basic_salary', 15, 2)->nullable()->after('prorate_ratio');
            $table->decimal('prorated_basic_salary', 15, 2)->nullable()->after('full_basic_salary');

            // Daily allowance
            $table->decimal('daily_allowance_total', 15, 2)->nullable()->after('prorated_basic_salary');

            // BPJS company paid tracking
            $table->boolean('bpjs_company_paid')->default(false)->after('daily_allowance_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'total_working_days',
                'actual_days_worked',
                'prorate_ratio',
                'full_basic_salary',
                'prorated_basic_salary',
                'daily_allowance_total',
                'bpjs_company_paid',
            ]);
        });
    }
};
