<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#6B7280'); // Default gray color
            $table->timestamps();
        });

        // Insert default priorities
        DB::table('ticket_priorities')->insert([
            [
                'name' => 'Low',
                'color' => '#10B981', // Green
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Medium',
                'color' => '#F59E0B', // Yellow
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'High',
                'color' => '#EF4444', // Red
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_priorities');
    }
};
