<?php

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\ProjectDocumentStorageService;
use Database\Seeders\ProjectDocumentPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('private');
    $this->seed(ProjectDocumentPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->project = Project::factory()->create();
});

it('logs an upload activity with project_id and metadata', function () {
    $uploader = projectMemberWith($this->project, ['create_project_document']);

    $doc = app(ProjectDocumentStorageService::class)->store(
        $this->project,
        UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.7'),
        'contract',
        null,
        $uploader,
    );

    $log = ActivityLog::where('event_type', 'project_document_uploaded')->first();
    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(ProjectDocument::class)
        ->and($log->subject_id)->toBe($doc->id)
        ->and($log->project_id)->toBe($this->project->id)
        ->and($log->user_id)->toBe($uploader->id)
        ->and($log->meta['category'])->toBe('contract');
});

it('logs a download activity', function () {
    $member = projectMemberWith($this->project, ['download_project_document']);
    $doc = ProjectDocument::factory()->for($this->project)->create([
        'file_path' => "project-documents/{$this->project->id}/x.pdf",
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('private')->put($doc->file_path, '%PDF-1.7');

    $this->actingAs($member)->get("/admin/projects/{$this->project->id}/documents/{$doc->id}/download")->assertOk();

    expect(ActivityLog::where('event_type', 'project_document_downloaded')->where('subject_id', $doc->id)->exists())->toBeTrue();
});

it('logs an archive activity with the reason', function () {
    $actor = projectMemberWith($this->project, ['delete_project_document']);
    $doc = ProjectDocument::factory()->for($this->project)->create();

    app(ProjectDocumentStorageService::class)->archive($doc, $actor, 'superseded by v2');

    $log = ActivityLog::where('event_type', 'project_document_archived')->where('subject_id', $doc->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->meta['reason'])->toBe('superseded by v2');
});
