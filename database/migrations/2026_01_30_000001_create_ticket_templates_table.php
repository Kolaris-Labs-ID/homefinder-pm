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
        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->enum('frequency', ['weekly', 'monthly']);
            $table->unsignedTinyInteger('day_value'); // 1-7 for weekly, 1-31 for monthly
            $table->unsignedTinyInteger('lead_time_days')->default(0); // 0-30
            $table->foreignId('initial_status_id')
                ->constrained('ticket_statuses')
                ->onDelete('cascade');
            $table->foreignId('priority_id')
                ->nullable()
                ->constrained('ticket_priorities')
                ->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamps();

            $table->index(['is_active', 'frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_templates');
    }
};
