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
        // Modify the enum to allow 'web'
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE hris_push_tokens MODIFY COLUMN platform ENUM('ios', 'android', 'web') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE hris_push_tokens MODIFY COLUMN platform ENUM('ios', 'android') NOT NULL");
        }
    }
};
