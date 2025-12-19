<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds workflow_snapshot column to store the workflow configuration
     * at the time of approval initialization. This ensures in-progress approvals
     * continue using their original workflow configuration even if the workflow
     * is modified later.
     */
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->json('workflow_snapshot')->nullable()->after('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('workflow_snapshot');
        });
    }
};
