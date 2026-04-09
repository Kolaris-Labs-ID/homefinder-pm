<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_pay_compensation_batches', function (Blueprint $table) {
            $table->id();

            $table->date('scheduled_pay_date');
            $table->date('actual_pay_date');
            $table->date('compensation_transfer_date')->nullable();

            $table->unsignedInteger('days_late')->default(0);
            $table->decimal('rate_percent', 5, 2)->default(1.00);

            $table->enum('status', [
                'draft',
                'calculated',
                'pending_finance',
                'rejected',
                'paid',
            ])->default('draft');

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();

            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->integer('total_employees')->default(0);
            $table->integer('employees_with_warnings')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->integer('current_finance_step_order')->nullable();

            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_pay_date');
            $table->index('actual_pay_date');
            $table->unique(['scheduled_pay_date', 'actual_pay_date'], 'lpc_batch_scheduled_actual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_pay_compensation_batches');
    }
};
