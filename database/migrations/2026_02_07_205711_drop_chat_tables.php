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
        Schema::dropIfExists('chat_group_messages');
        Schema::dropIfExists('chat_group_members');
        Schema::dropIfExists('chat_groups');
        Schema::dropIfExists('ch_messages');
        Schema::dropIfExists('ch_favorites');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Chat feature has been permanently removed.
        // Tables cannot be recreated from this migration.
    }
};
