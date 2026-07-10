<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // enum('office','wfh') → string so 'wfa' becomes storable.
        // Mode validity is enforced at the application layer.
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('mode', 20)->default('office')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('mode', 20)->default('office')->change();
        });
    }
};
