<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_authorization_settings', 'high_value_threshold')) {
            Schema::table('payment_authorization_settings', function (Blueprint $table) {
                $table->dropColumn('high_value_threshold');
            });
        }
    }

    public function down(): void
    {
        Schema::table('payment_authorization_settings', function (Blueprint $table) {
            $table->decimal('high_value_threshold', 15, 2)->default(10000000.00);
        });
    }
};
