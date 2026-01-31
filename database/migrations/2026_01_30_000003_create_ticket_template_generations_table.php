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
        Schema::create('ticket_template_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_template_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('ticket_id')
                ->constrained()
                ->onDelete('cascade');
            $table->date('target_date');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['ticket_template_id', 'target_date'], 'tt_generations_template_target_unique');
            $table->index('target_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_template_generations');
    }
};
