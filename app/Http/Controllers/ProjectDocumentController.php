<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    private const INLINE_MIMES = ['application/pdf', 'image/png', 'image/jpeg'];

    public function view(Project $project, ProjectDocument $document): StreamedResponse
    {
        $this->ensureBelongs($project, $document);
        $this->authorize('view', $document);

        return $this->stream($document, inline: in_array($document->mime_type, self::INLINE_MIMES, true));
    }

    public function download(Project $project, ProjectDocument $document): StreamedResponse
    {
        $this->ensureBelongs($project, $document);
        $this->authorize('download', $document);

        ActivityLog::recordForSubject($document, 'project_document_downloaded', request()->user(), [
            'category' => $document->category,
            'original_filename' => $document->original_filename,
        ]);

        return $this->stream($document, inline: false);
    }

    private function ensureBelongs(Project $project, ProjectDocument $document): void
    {
        abort_unless($document->project_id === $project->id, 404);
    }

    private function stream(ProjectDocument $document, bool $inline): StreamedResponse
    {
        $disk = Storage::disk($document->disk ?: 'private');
        abort_unless($disk->exists($document->file_path), 404);

        $name = $document->original_filename ?: 'document';
        $disposition = $inline ? 'inline' : 'attachment';

        return $disk->response($document->file_path, $name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($name).'"',
        ]);
    }
}
