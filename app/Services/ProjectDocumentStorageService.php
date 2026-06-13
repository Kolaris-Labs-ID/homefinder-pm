<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectDocumentStorageService
{
    public const DISK = 'private';

    public const MAX_BYTES = 10_485_760; // 10 MB

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];

    public function store(Project $project, UploadedFile $file, string $category, ?string $description, User $uploader): ProjectDocument
    {
        $this->validate($file, $category);

        $content = (string) file_get_contents($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension());
        $path = "project-documents/{$project->id}/".Str::uuid()->toString().'.'.$ext;

        Storage::disk(self::DISK)->put($path, $content);

        try {
            $document = ProjectDocument::create([
                'project_id' => $project->id,
                'uploaded_by' => $uploader->id,
                'disk' => self::DISK,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => strlen($content),
                'file_hash' => hash('sha256', $content),
                'category' => $category,
                'description' => $description,
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }

        ActivityLog::recordForSubject($document, 'project_document_uploaded', $uploader, [
            'category' => $document->category,
            'original_filename' => $document->original_filename,
            'size_bytes' => $document->size_bytes,
            'mime_type' => $document->mime_type,
        ]);

        return $document;
    }

    public function archive(ProjectDocument $document, User $actor, ?string $reason = null): void
    {
        $document->update([
            'archived_at' => now(),
            'archived_by' => $actor->id,
            'archive_reason' => $reason,
        ]);

        ActivityLog::recordForSubject($document, 'project_document_archived', $actor, [
            'category' => $document->category,
            'original_filename' => $document->original_filename,
            'reason' => $reason,
        ]);
    }

    protected function validate(UploadedFile $file, string $category): void
    {
        if (! in_array($category, ProjectDocument::CATEGORIES, true)) {
            throw new \RuntimeException('Invalid document category.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \RuntimeException('Document exceeds the 10 MB limit.');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Unsupported file type.');
        }

        $header = (string) file_get_contents($file->getRealPath(), false, null, 0, 8);
        if (! $this->signatureMatchesExtension($ext, $header)) {
            throw new \RuntimeException('File content does not match its extension.');
        }
    }

    // Content sniff (magic bytes) so a renamed file cannot smuggle a disallowed type past the extension check.
    protected function signatureMatchesExtension(string $ext, string $header): bool
    {
        return match ($ext) {
            'pdf' => str_starts_with($header, '%PDF'),
            'png' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            'jpg', 'jpeg' => str_starts_with($header, "\xFF\xD8\xFF"),
            'doc', 'xls' => str_starts_with($header, "\xD0\xCF\x11\xE0"),       // OLE2 compound
            'docx', 'xlsx' => str_starts_with($header, "PK\x03\x04"),           // OOXML zip
            default => false,
        };
    }
}
