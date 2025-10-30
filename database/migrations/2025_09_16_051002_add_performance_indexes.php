<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Index for project board queries
            if (!$this->indexExists('tickets', 'idx_tickets_project_status')) {
                $table->index(['project_id', 'ticket_status_id'], 'idx_tickets_project_status');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_status_created')) {
                $table->index(['ticket_status_id', 'created_at'], 'idx_tickets_status_created');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_project_created')) {
                $table->index(['project_id', 'created_at'], 'idx_tickets_project_created');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_project_updated')) {
                $table->index(['project_id', 'updated_at'], 'idx_tickets_project_updated');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_due_date')) {
                $table->index(['due_date'], 'idx_tickets_due_date');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_priority')) {
                $table->index(['priority_id'], 'idx_tickets_priority');
            }
            if (!$this->indexExists('tickets', 'idx_tickets_created_by')) {
                $table->index(['created_by'], 'idx_tickets_created_by');
            }
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            // Index for project board status queries
            if (!$this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_sort')) {
                $table->index(['project_id', 'sort_order'], 'idx_ticket_statuses_project_sort');
            }
            if (!$this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_completed')) {
                $table->index(['project_id', 'is_completed'], 'idx_ticket_statuses_project_completed');
            }
        });

        Schema::table('ticket_users', function (Blueprint $table) {
            // Index for assignee queries
            if (!$this->indexExists('ticket_users', 'idx_ticket_users_ticket_user')) {
                $table->index(['ticket_id', 'user_id'], 'idx_ticket_users_ticket_user');
            }
            if (!$this->indexExists('ticket_users', 'idx_ticket_users_user')) {
                $table->index(['user_id'], 'idx_ticket_users_user');
            }
        });

        Schema::table('project_members', function (Blueprint $table) {
            // Index for project member queries
            if (!$this->indexExists('project_members', 'idx_project_members_project_user')) {
                $table->index(['project_id', 'user_id'], 'idx_project_members_project_user');
            }
            if (!$this->indexExists('project_members', 'idx_project_members_user')) {
                $table->index(['user_id'], 'idx_project_members_user');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            // Index for project queries
            if (!$this->indexExists('projects', 'idx_projects_pinned')) {
                $table->index(['pinned_date'], 'idx_projects_pinned');
            }
            if (!$this->indexExists('projects', 'idx_projects_dates')) {
                $table->index(['start_date', 'end_date'], 'idx_projects_dates');
            }
        });

        Schema::table('ticket_priorities', function (Blueprint $table) {
            // Index for priority queries
            if (!$this->indexExists('ticket_priorities', 'idx_ticket_priorities_name')) {
                $table->index(['name'], 'idx_ticket_priorities_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if ($this->indexExists('tickets', 'idx_tickets_project_status')) {
                $table->dropIndex('idx_tickets_project_status');
            }
            if ($this->indexExists('tickets', 'idx_tickets_status_created')) {
                $table->dropIndex('idx_tickets_status_created');
            }
            if ($this->indexExists('tickets', 'idx_tickets_project_created')) {
                $table->dropIndex('idx_tickets_project_created');
            }
            if ($this->indexExists('tickets', 'idx_tickets_project_updated')) {
                $table->dropIndex('idx_tickets_project_updated');
            }
            if ($this->indexExists('tickets', 'idx_tickets_due_date')) {
                $table->dropIndex('idx_tickets_due_date');
            }
            if ($this->indexExists('tickets', 'idx_tickets_priority')) {
                $table->dropIndex('idx_tickets_priority');
            }
            if ($this->indexExists('tickets', 'idx_tickets_created_by')) {
                $table->dropIndex('idx_tickets_created_by');
            }
        });

        Schema::table('ticket_statuses', function (Blueprint $table) {
            if ($this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_sort')) {
                $table->dropIndex('idx_ticket_statuses_project_sort');
            }
            if ($this->indexExists('ticket_statuses', 'idx_ticket_statuses_project_completed')) {
                $table->dropIndex('idx_ticket_statuses_project_completed');
            }
        });

        Schema::table('ticket_users', function (Blueprint $table) {
            if ($this->indexExists('ticket_users', 'idx_ticket_users_ticket_user')) {
                $table->dropIndex('idx_ticket_users_ticket_user');
            }
            if ($this->indexExists('ticket_users', 'idx_ticket_users_user')) {
                $table->dropIndex('idx_ticket_users_user');
            }
        });

        Schema::table('project_members', function (Blueprint $table) {
            if ($this->indexExists('project_members', 'idx_project_members_project_user')) {
                $table->dropIndex('idx_project_members_project_user');
            }
            if ($this->indexExists('project_members', 'idx_project_members_user')) {
                $table->dropIndex('idx_project_members_user');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if ($this->indexExists('projects', 'idx_projects_pinned')) {
                $table->dropIndex('idx_projects_pinned');
            }
            if ($this->indexExists('projects', 'idx_projects_dates')) {
                $table->dropIndex('idx_projects_dates');
            }
        });

        Schema::table('ticket_priorities', function (Blueprint $table) {
            if ($this->indexExists('ticket_priorities', 'idx_ticket_priorities_name')) {
                $table->dropIndex('idx_ticket_priorities_name');
            }
        });
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL version
            return DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $indexName)
                ->exists();
        }

        if ($driver === 'mysql') {
            // MySQL version
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        }

        // Default fallback (other drivers)
        return false;
    }
};
