<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_authorization_settings', function (Blueprint $table) {
            $table->json('pr_rejecter_user_ids')->nullable()->after('executive_signer_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_authorization_settings', function (Blueprint $table) {
            $table->dropColumn('pr_rejecter_user_ids');
        });
    }
};
