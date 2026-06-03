<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Statuses where the source is no longer "held" by this PR (mirrors PaymentRequestService::INACTIVE_STATUSES).
    // Active = NOT IN this list.
    private const INACTIVE = ['rejected_by_coo', 'voided', 'cancelled', 'reversed'];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            // SQLite/PostgreSQL: partial WHERE index.
            // NULL source_id rows (manual PRs) are excluded from the index because
            // SQLite NULL != NULL semantics mean they never match the index — but to be
            // explicit and robust we also exclude source_type = 'manual'.
            DB::statement(
                "CREATE UNIQUE INDEX payment_requests_active_source_unique
                ON payment_requests (source_type, source_id)
                WHERE source_id IS NOT NULL
                  AND status NOT IN ('rejected_by_coo', 'voided', 'cancelled', 'reversed')"
            );

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // MySQL 8: no partial WHERE index support — use a generated column.
            // NULL source_id → generated column is NULL → excluded from UNIQUE (MySQL NULL ≠ NULL).
            // Inactive statuses → generated column is NULL → also excluded.
            DB::statement(
                "ALTER TABLE payment_requests
                ADD COLUMN active_source_key VARCHAR(255)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN source_id IS NOT NULL
                             AND status NOT IN ('rejected_by_coo', 'voided', 'cancelled', 'reversed')
                            THEN CONCAT(source_type, '#', source_id)
                            ELSE NULL
                        END
                    ) STORED,
                ADD UNIQUE INDEX payment_requests_active_source_unique (active_source_key)"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS payment_requests_active_source_unique');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE payment_requests DROP INDEX payment_requests_active_source_unique');
            Schema::table('payment_requests', function ($table) {
                $table->dropColumn('active_source_key');
            });
        }
    }
};
