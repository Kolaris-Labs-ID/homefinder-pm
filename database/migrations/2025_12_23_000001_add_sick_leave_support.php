<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add requires_attachment field to leave_types
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('requires_attachment')->default(false)->after('requires_approval');
        });

        // Add attachment_path field to leave_requests
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('requires_attachment');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};
