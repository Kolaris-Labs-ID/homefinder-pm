<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_signer_1_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_signer_2_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executive_signer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('require_two_finance_signatures')->default(true);
            $table->boolean('require_executive_signature')->default(true);
            $table->decimal('high_value_threshold', 15, 2)->default(10000000.00);
            $table->boolean('allow_creator_to_sign')->default(false);
            $table->boolean('allow_executor_to_sign')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_authorization_settings');
    }
};
