<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('private');
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('file_hash', 64)->index();
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
