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
        Schema::table('salary_structures', function (Blueprint $table) {
            // Daily allowance rate (Uang Harian per hari)
            $table->decimal('daily_allowance_rate', 15, 2)->nullable()->after('deductions');

            // Prorate enabled flag
            $table->boolean('prorate_enabled')->default(true)->after('daily_allowance_rate');

            // BPJS paid by option
            $table->enum('bpjs_paid_by', ['employee', 'company', 'shared'])->default('employee')->after('prorate_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_structures', function (Blueprint $table) {
            $table->dropColumn([
                'daily_allowance_rate',
                'prorate_enabled',
                'bpjs_paid_by',
            ]);
        });
    }
};
