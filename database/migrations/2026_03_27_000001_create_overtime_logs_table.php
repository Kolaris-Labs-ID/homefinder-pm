<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_logs', function (Blueprint $table) {
            $table->id();

            // Employee who worked overtime
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Manager/assigner
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();

            // Task details
            $table->string('task_name');
            $table->text('description')->nullable();

            // Client (free text, nullable — no master table)
            $table->string('client_name')->nullable();

            // Timer
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);

            // Pay — stored per-entry so HR can override
            $table->decimal('hourly_rate', 12, 2)->default(0);

            // Evidence photo path or URL
            $table->string('evidence_path')->nullable();

            // Workflow
            $table->enum('status', ['running', 'pending', 'approved', 'rejected', 'flagged'])
                ->default('running');
            $table->text('rejection_note')->nullable();

            // Flag for auto-close review (set by LemburAutoClose command)
            $table->boolean('auto_closed')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_logs');
    }
};
