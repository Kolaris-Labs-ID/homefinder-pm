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
        // Push tokens table
        if (!Schema::hasTable('hris_push_tokens')) {
            Schema::create('hris_push_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('token');
                $table->enum('platform', ['ios', 'android']);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['employee_id', 'platform']);
                $table->index('token');
            });
        }

        // Notification settings table
        if (!Schema::hasTable('hris_notification_settings')) {
            Schema::create('hris_notification_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->boolean('enabled')->default(true);
                $table->boolean('check_in_reminder')->default(true);
                $table->boolean('check_out_reminder')->default(true);
                $table->boolean('break_reminder')->default(true);
                $table->boolean('announcements')->default(true);
                $table->boolean('leave_updates')->default(true);
                $table->boolean('reimbursement_updates')->default(true);
                $table->boolean('payslip_notifications')->default(true);
                $table->timestamps();

                $table->unique('employee_id');
            });
        }

        // User notifications table
        if (!Schema::hasTable('hris_user_notifications')) {
            Schema::create('hris_user_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
                $table->string('type'); // check_in_reminder, leave_update, announcement, etc.
                $table->string('title');
                $table->text('body');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'read_at']);
                $table->index(['employee_id', 'created_at']);
            });
        }

        // Add foreign key constraints if tables were created previously without them
        if (Schema::hasTable('hris_push_tokens') && !$this->hasForeignKey('hris_push_tokens', 'hris_push_tokens_employee_id_foreign')) {
            Schema::table('hris_push_tokens', function (Blueprint $table) {
                $table->foreign('employee_id', 'hris_push_tokens_employee_id_foreign')
                    ->references('id')->on('employees')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('hris_notification_settings') && !$this->hasForeignKey('hris_notification_settings', 'hris_notification_settings_employee_id_foreign')) {
            Schema::table('hris_notification_settings', function (Blueprint $table) {
                $table->foreign('employee_id', 'hris_notification_settings_employee_id_foreign')
                    ->references('id')->on('employees')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('hris_user_notifications') && !$this->hasForeignKey('hris_user_notifications', 'hris_user_notifications_employee_id_foreign')) {
            Schema::table('hris_user_notifications', function (Blueprint $table) {
                $table->foreign('employee_id', 'hris_user_notifications_employee_id_foreign')
                    ->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    /**
     * Check if a foreign key exists on a table.
     */
    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();

        $result = $conn->select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ?
             AND TABLE_NAME = ?
             AND CONSTRAINT_NAME = ?
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$dbName, $table, $foreignKey]
        );

        return count($result) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hris_user_notifications');
        Schema::dropIfExists('hris_notification_settings');
        Schema::dropIfExists('hris_push_tokens');
    }
};
