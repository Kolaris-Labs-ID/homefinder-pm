<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_number')->unique();
            $table->string('title')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('bundle_version')->default(1);

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('IDR');

            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('authorized_at')->nullable();

            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->unsignedBigInteger('payment_proof_attachment_id')->nullable();
            $table->text('payment_notes')->nullable();

            // Canonical bundle-level payload/hash — populated in Phase 4 (NULL here).
            $table->longText('canonical_payload_json')->nullable();
            $table->string('canonical_payload_hash', 64)->nullable()->index();

            $table->unsignedBigInteger('unsigned_pdf_attachment_id')->nullable();
            $table->string('unsigned_pdf_file_hash', 64)->nullable();
            $table->unsignedBigInteger('signed_pdf_attachment_id')->nullable();
            $table->string('signed_pdf_file_hash', 64)->nullable();
            $table->string('signed_payload_hash', 64)->nullable();

            // Forward-support for the public verification page (Phase 12).
            $table->string('public_verification_code', 64)->unique()->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_bundles');
    }
};
