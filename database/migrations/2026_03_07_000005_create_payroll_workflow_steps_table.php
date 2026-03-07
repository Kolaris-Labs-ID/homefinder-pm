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
        Schema::create('payroll_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_workflow_config_id')
                ->constrained('payroll_workflow_configs')
                ->cascadeOnDelete();
            $table->string('step_type', 20);
            $table->string('assignee_type', 10);
            $table->foreignId('assignee_user_id')
                ->nullable()
                ->constrained('users');
            $table->string('assignee_role', 50)->nullable();
            $table->integer('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_workflow_steps');
    }
};
