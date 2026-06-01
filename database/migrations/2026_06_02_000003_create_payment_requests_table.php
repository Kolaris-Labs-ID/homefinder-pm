<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('payment_request_number')->unique();
            $table->string('source_type')->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_executor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('payee_type')->nullable();
            $table->string('payee_name');
            $table->string('bank_name')->nullable();
            $table->text('bank_account_number_encrypted')->nullable();
            $table->string('bank_account_last4', 4)->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->string('original_currency', 3)->nullable();
            $table->decimal('fx_rate', 18, 6)->nullable();

            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft')->index();

            $table->json('approval_snapshot_json')->nullable();
            $table->string('approval_snapshot_hash', 64)->nullable();

            $table->timestamp('submitted_to_coo_at')->nullable();
            $table->foreignId('coo_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('coo_approved_at')->nullable();
            $table->text('coo_rejection_reason')->nullable();

            $table->timestamp('scheduled_for_payment_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('payment_proof_attachment_id')->nullable();

            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_reference')->nullable();
            $table->unsignedBigInteger('bank_statement_attachment_id')->nullable();

            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();

            // Withholding (Phase 13)
            $table->boolean('withholding_required')->default(false);
            $table->string('withholding_type')->nullable();
            $table->decimal('withholding_amount', 15, 2)->nullable();
            $table->text('tax_notes')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
