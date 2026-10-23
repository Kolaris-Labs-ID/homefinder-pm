<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per flag, written once at first setup (app:init or the Company
        // Features page). No row means "not chosen yet".
        Schema::create('app_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->string('source', 100);
            $table->timestamp('initialized_at');
            $table->timestamps();
        });

        Schema::create('app_feature_changes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->json('old_value')->nullable();
            $table->json('new_value');
            $table->text('reason');
            $table->string('actor', 100);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_feature_changes');
        Schema::dropIfExists('app_features');
    }
};
