<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_bundle_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_bundle_id')->constrained('payment_bundles')->cascadeOnDelete();

            $table->string('signature_slot');
            $table->string('required_permission');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('signature_token_hash', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_used_at')->nullable();
            $table->timestamp('token_revoked_at')->nullable();

            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('signature_image_attachment_id')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('signature_role')->nullable();
            $table->string('signature_ip')->nullable();
            $table->text('signature_user_agent')->nullable();
            $table->string('signature_hash', 64)->nullable();

            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['payment_bundle_id', 'signature_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_bundle_signatures');
    }
};
