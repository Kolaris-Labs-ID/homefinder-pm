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
        // Update all existing tickets to set start_date = created_at date
        DB::table('tickets')
            ->whereNull('start_date')
            ->update([
                'start_date' => DB::raw('DATE(created_at)')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set start_date back to null for all tickets
        DB::table('tickets')
            ->update(['start_date' => null]);
    }
};