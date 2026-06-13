<?php

use App\Models\ExternalAccess;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->project = Project::factory()->create();
    $this->token = 'ext-token-'.uniqid();
    ExternalAccess::create([
        'project_id' => $this->project->id,
        'access_token' => $this->token,
        'password' => 'secret123',
        'is_active' => true,
    ]);

    $this->document = ProjectDocument::factory()->for($this->project)->create([
        'file_path' => "project-documents/{$this->project->id}/file.pdf",
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('private')->put($this->document->file_path, '%PDF-1.7 body');
});

$viewUrl = fn (ProjectDocument $d) => "/admin/projects/{$d->project_id}/documents/{$d->id}/view";
$downloadUrl = fn (ProjectDocument $d) => "/admin/projects/{$d->project_id}/documents/{$d->id}/download";

it('blocks a valid external dashboard session from viewing a document', function () use ($viewUrl) {
    $this->withSession(['external_authenticated_'.$this->token => true])
        ->get($viewUrl($this->document))
        ->assertRedirectToRoute('login');
});

it('blocks a valid external dashboard session from downloading a document', function () use ($downloadUrl) {
    $this->withSession(['external_authenticated_'.$this->token => true])
        ->get($downloadUrl($this->document))
        ->assertRedirectToRoute('login');
});

it('blocks a fully unauthenticated visitor from documents', function () use ($viewUrl) {
    $this->get($viewUrl($this->document))->assertRedirectToRoute('login');
});
