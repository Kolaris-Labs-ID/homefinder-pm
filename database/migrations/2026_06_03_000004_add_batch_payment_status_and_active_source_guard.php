<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $batchTables = [
        'payrun_batches',
        'weekly_allowance_batches',
        'overtime_pay_batches',
        'late_pay_compensation_batches',
    ];

    public function up(): void
    {
        foreach ($this->batchTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('payment_status')->nullable()->after('status')->index();
            });
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX payment_bundle_items_active_source_unique
                ON payment_bundle_items (source_type, source_id)
                WHERE status IN ('payment_bundled', 'payment_authorized', 'payment_recording')"
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE payment_bundle_items
                ADD COLUMN active_source_key VARCHAR(255)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN status IN ('payment_bundled', 'payment_authorized', 'payment_recording')
                            THEN CONCAT(source_type, '#', source_id)
                            ELSE NULL
                        END
                    ) STORED,
                ADD UNIQUE INDEX payment_bundle_items_active_source_unique (active_source_key)"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS payment_bundle_items_active_source_unique');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payment_bundle_items DROP INDEX payment_bundle_items_active_source_unique');
            Schema::table('payment_bundle_items', function (Blueprint $table) {
                $table->dropColumn('active_source_key');
            });
        }

        foreach ($this->batchTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });
        }
    }
};
