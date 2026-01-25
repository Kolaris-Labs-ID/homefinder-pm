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
        Schema::create('weekly_allowance_payments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('weekly_allowance_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Rate snapshot (from salary structure at time of generation)
            $table->decimal('daily_rate', 15, 2);

            // Attendance breakdown
            $table->integer('days_present')->default(0);
            $table->json('days_breakdown')->nullable(); // {"mon": true, "tue": false, "wed": true, "thu": true, "fri": true}

            // Calculated amount
            $table->decimal('total_amount', 15, 2)->default(0);

            // Bank info snapshot (from employee at time of generation)
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();

            // Warning flags
            $table->boolean('has_bank_warning')->default(false);

            // Status for individual payment
            $table->enum('status', ['pending', 'approved', 'excluded', 'paid'])->default('pending');

            // Notes
            $table->text('hr_notes')->nullable();
            $table->text('finance_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->unique(['weekly_allowance_batch_id', 'employee_id'], 'wa_payments_batch_employee_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_allowance_payments');
    }
};
