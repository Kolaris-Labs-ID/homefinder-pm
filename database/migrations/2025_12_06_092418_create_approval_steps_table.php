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
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('approval_workflows')->onDelete('cascade');
            $table->morphs('approvable');
            $table->integer('step_order');
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('approver_role')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'step_order']);
            $table->index(['approver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
    }
};
