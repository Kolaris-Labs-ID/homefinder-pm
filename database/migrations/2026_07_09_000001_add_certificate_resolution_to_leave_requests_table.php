<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resolution outcome of the 48h certificate flow: when no valid certificate
     * arrives, HR converts quota-covered days to Annual Leave and the remainder
     * becomes alpha. The converted annual request points back via
     * converted_from_leave_request_id.
     */
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('certificate_resolution', 20)->nullable()->after('certificate_rejection_reason');
            $table->timestamp('certificate_resolved_at')->nullable()->after('certificate_resolution');
            $table->foreignId('certificate_resolved_by')->nullable()->after('certificate_resolved_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('converted_from_leave_request_id')->nullable()->after('certificate_resolved_by')
                ->constrained('leave_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_from_leave_request_id');
            $table->dropConstrainedForeignId('certificate_resolved_by');
            $table->dropColumn(['certificate_resolved_at', 'certificate_resolution']);
        });
    }
};
