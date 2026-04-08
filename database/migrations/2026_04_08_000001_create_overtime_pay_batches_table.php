<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_pay_batches', function (Blueprint $table) {
            $table->id();

            // Week period (Mon-Sun or Mon-Fri)
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->date('payment_date');

            // Status workflow: draft -> calculated -> pending_finance -> paid | rejected
            $table->enum('status', [
                'draft',
                'calculated',
                'pending_finance',
                'rejected',
                'paid',
            ])->default('draft');

            // Generation tracking
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();

            // Submission
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            // Finance approval (multi-step)
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();

            // Payment tracking
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            // Summary fields
            $table->integer('total_employees')->default(0);
            $table->integer('employees_with_warnings')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Multi-step finance approval tracking
            $table->integer('current_finance_step_order')->nullable();

            // Notes
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('week_start_date');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_pay_batches');
    }
};
