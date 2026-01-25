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
        Schema::create('weekly_allowance_batches', function (Blueprint $table) {
            $table->id();

            // Week period (Mon-Fri)
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->date('payment_date'); // Tuesday

            // Status workflow: draft -> pending_hr -> pending_finance -> pending_final -> approved -> paid
            $table->enum('status', [
                'draft',
                'pending_hr',
                'pending_finance',
                'pending_final',
                'approved',
                'paid',
                'cancelled',
            ])->default('draft');

            // Generation tracking
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();

            // HR review
            $table->foreignId('hr_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_reviewed_at')->nullable();

            // Finance approval
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_approved_at')->nullable();

            // Final approval (COO or configurable)
            $table->foreignId('final_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('final_approved_at')->nullable();

            // Payment tracking
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            // Summary fields
            $table->integer('total_employees')->default(0);
            $table->integer('employees_with_warnings')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Notes
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('week_start_date');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_allowance_batches');
    }
};
