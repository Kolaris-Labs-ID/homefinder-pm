<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payrun_batch_id')->constrained('payrun_batches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Salary components
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_fixed_allowances', 15, 2)->default(0);
            $table->decimal('total_variable_allowances', 15, 2)->default(0);
            $table->decimal('gross_income', 15, 2);
            $table->decimal('taxable_income', 15, 2);

            // BPJS Kesehatan
            $table->decimal('bpjs_kesehatan_employee', 15, 2)->default(0);
            $table->decimal('bpjs_kesehatan_company', 15, 2)->default(0);

            // BPJS Ketenagakerjaan - JHT
            $table->decimal('bpjs_jht_employee', 15, 2)->default(0);
            $table->decimal('bpjs_jht_company', 15, 2)->default(0);

            // BPJS Ketenagakerjaan - JP
            $table->decimal('bpjs_jp_employee', 15, 2)->default(0);
            $table->decimal('bpjs_jp_company', 15, 2)->default(0);

            // BPJS Ketenagakerjaan - JKK & JKM (company only)
            $table->decimal('bpjs_jkk_company', 15, 2)->default(0);
            $table->decimal('bpjs_jkm_company', 15, 2)->default(0);

            // Tax and deductions
            $table->decimal('pph21_amount', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);

            // Final amounts
            $table->decimal('net_income', 15, 2);

            // Detailed breakdown as JSON
            $table->json('details')->nullable();

            $table->timestamps();

            // Unique constraint - one record per employee per batch
            $table->unique(['payrun_batch_id', 'employee_id']);

            // Indexes for query performance
            $table->index('employee_id');
            $table->index('payrun_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
