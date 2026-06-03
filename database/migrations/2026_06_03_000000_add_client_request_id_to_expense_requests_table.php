<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->string('client_request_id', 64)->nullable()->after('request_number');
            $table->unique(['employee_id', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'client_request_id']);
            $table->dropColumn('client_request_id');
        });
    }
};
