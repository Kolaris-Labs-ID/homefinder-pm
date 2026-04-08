<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('overtime_pay_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Aggregated from approved OvertimeLogs for the week
            $table->integer('total_sessions')->default(0);
            $table->integer('total_duration_minutes')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Snapshot of individual sessions (JSON array of overtime_log ids and amounts)
            $table->json('sessions_breakdown')->nullable();

            // Bank info snapshot
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->boolean('has_bank_warning')->default(false);

            // Individual payment status
            $table->enum('status', ['pending', 'approved', 'excluded', 'paid'])->default('pending');

            // Notes
            $table->text('hr_notes')->nullable();
            $table->text('finance_notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->unique(['overtime_pay_batch_id', 'employee_id'], 'op_batch_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_payments');
    }
};
