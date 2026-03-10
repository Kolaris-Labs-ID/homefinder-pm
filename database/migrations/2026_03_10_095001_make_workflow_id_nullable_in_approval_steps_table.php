<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make workflow_id nullable so that leave requests without
     * an explicit approval workflow can still create ApprovalStep
     * records for the Approval Dashboard.
     */
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['workflow_id']);

            // Make the column nullable and re-add the foreign key as nullable
            $table->foreignId('workflow_id')
                ->nullable()
                ->change();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('approval_workflows')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);

            $table->foreignId('workflow_id')
                ->nullable(false)
                ->change();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('approval_workflows')
                ->onDelete('cascade');
        });
    }
};
