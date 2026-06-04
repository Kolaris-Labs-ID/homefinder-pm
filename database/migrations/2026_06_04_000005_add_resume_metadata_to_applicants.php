<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('resume_disk')->nullable()->after('resume_file_path');
            $table->string('resume_original_filename')->nullable()->after('resume_disk');
            $table->string('resume_mime_type')->nullable()->after('resume_original_filename');
            $table->unsignedBigInteger('resume_size_bytes')->nullable()->after('resume_mime_type');
            $table->string('resume_file_hash', 64)->nullable()->after('resume_size_bytes');
            $table->timestamp('resume_uploaded_at')->nullable()->after('resume_file_hash');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'resume_disk', 'resume_original_filename', 'resume_mime_type',
                'resume_size_bytes', 'resume_file_hash', 'resume_uploaded_at',
            ]);
        });
    }
};
