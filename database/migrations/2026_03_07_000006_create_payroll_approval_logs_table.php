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
        Schema::create('payroll_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_type');
            $table->unsignedBigInteger('batch_id');
            $table->string('action', 20);
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->foreignId('acted_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['batch_type', 'batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_approval_logs');
    }
};
