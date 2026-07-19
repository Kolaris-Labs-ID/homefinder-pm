<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile app submits free-text visit purposes, but the column was an
 * ENUM of 7 fixed slugs — any other value made POST /api/hris/visits 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_visits', function (Blueprint $table) {
            $table->string('purpose')->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_visits', function (Blueprint $table) {
            $table->enum('purpose', [
                'accounting_training',
                'tax_consultation',
                'company_establishment',
                'legal_permits',
                'business_permits',
                'audit',
                'other',
            ])->change();
        });
    }
};
