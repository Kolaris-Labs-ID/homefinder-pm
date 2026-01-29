<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_records', function (Blueprint $table) {
            $table->enum('training_type', ['free', 'paid'])->default('free')->after('status');
            $table->unsignedTinyInteger('pgp_points')->nullable()->after('training_type');
            $table->timestamp('approved_at')->nullable()->after('pgp_points');

            // Index for PGP queries
            $table->index('approved_at');
            $table->index('training_type');
        });
    }

    public function down(): void
    {
        Schema::table('training_records', function (Blueprint $table) {
            $table->dropIndex(['approved_at']);
            $table->dropIndex(['training_type']);
            $table->dropColumn(['training_type', 'pgp_points', 'approved_at']);
        });
    }
};
