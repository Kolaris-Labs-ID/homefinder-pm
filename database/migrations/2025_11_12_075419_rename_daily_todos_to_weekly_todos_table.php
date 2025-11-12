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
        // Rename table
        Schema::rename('daily_todos', 'weekly_todos');
        
        // Rename column
        Schema::table('weekly_todos', function (Blueprint $table) {
            $table->renameColumn('date', 'week_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename column back
        Schema::table('weekly_todos', function (Blueprint $table) {
            $table->renameColumn('week_start_date', 'date');
        });
        
        // Rename table back
        Schema::rename('weekly_todos', 'daily_todos');
    }
};
