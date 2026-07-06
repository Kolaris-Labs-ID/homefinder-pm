<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Provisional Sick Leave approval: status stays approved/rejected as today
     * (see LeaveRequest::STATUS_*) — these columns only track the 48h medical
     * certificate lifecycle alongside it, so existing status-based consumers
     * (AttendanceService, AttendancePayrollService, HRAnalyticsService, ...)
     * need no changes.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('certificate_status', 20)->nullable()->after('attachment_path');
            $table->timestamp('certificate_deadline_at')->nullable()->after('certificate_status');
            $table->timestamp('certificate_submitted_at')->nullable()->after('certificate_deadline_at');
            $table->timestamp('certificate_verified_at')->nullable()->after('certificate_submitted_at');
            $table->foreignId('certificate_verified_by')->nullable()->after('certificate_verified_at')->constrained('users')->nullOnDelete();
            $table->text('certificate_rejection_reason')->nullable()->after('certificate_verified_by');
            $table->boolean('auto_rejected')->default(false)->after('certificate_rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_verified_by');
            $table->dropColumn([
                'certificate_status',
                'certificate_deadline_at',
                'certificate_submitted_at',
                'certificate_verified_at',
                'certificate_rejection_reason',
                'auto_rejected',
            ]);
        });
    }
};
