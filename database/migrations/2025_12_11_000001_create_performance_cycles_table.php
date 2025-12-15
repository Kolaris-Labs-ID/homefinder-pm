<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();

            // Indexes for query performance
            $table->index('status');
            $table->index(['period_start_date', 'period_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_cycles');
    }
};
