<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('performance_cycles')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->decimal('target_value', 15, 2);
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            // Indexes for query performance
            $table->index('employee_id');
            $table->index('cycle_id');
            $table->index(['employee_id', 'cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
    }
};
