<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_bundle_id')->constrained('payment_bundles')->cascadeOnDelete();

            $table->string('source_type')->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->unsignedBigInteger('payment_request_id')->nullable()->index();
            $table->unsignedBigInteger('payment_request_line_id')->nullable();

            $table->text('description')->nullable();
            $table->string('payee_name');
            $table->string('payee_bank_name')->nullable();
            $table->string('payee_bank_account_last4', 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('IDR');

            // Frozen source snapshot incl. full bank account — encrypted at rest (model cast).
            $table->longText('source_snapshot_json');
            $table->string('source_hash', 64);

            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_bundle_items');
    }
};
