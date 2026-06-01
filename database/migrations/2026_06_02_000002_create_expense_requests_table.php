<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('category');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->decimal('fx_rate', 18, 6)->nullable();
            $table->string('fx_rate_source')->nullable();
            $table->timestamp('fx_rate_locked_at')->nullable();
            $table->date('expense_date');
            $table->string('payment_mode');
            $table->string('payee_name')->nullable();
            $table->string('payee_bank_name')->nullable();
            $table->text('payee_bank_account_number_encrypted')->nullable();
            $table->string('payee_bank_account_last4', 4)->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('finance_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finance_reviewed_at')->nullable();
            $table->text('finance_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('revision_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_requests');
    }
};
