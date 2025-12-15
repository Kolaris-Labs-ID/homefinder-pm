<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('basic_salary', 15, 2);

            // Allowances and deductions as JSON
            $table->json('allowances_fixed')->nullable();
            $table->json('allowances_variable')->nullable();
            $table->json('deductions')->nullable();

            // PPh 21 calculation method
            $table->enum('pph21_method', ['netto', 'gross', 'gross_up'])->default('gross');

            // JKK risk level for BPJS Ketenagakerjaan
            $table->enum('jkk_risk_level', ['very_low', 'low', 'medium', 'high', 'very_high'])->default('low');

            $table->timestamps();

            // Indexes for query performance
            $table->index('employee_id');
            $table->index('effective_date');
            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
