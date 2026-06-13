<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use Database\Seeders\ProjectDocumentPermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('private');
    $this->seed(ProjectDocumentPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->project = Project::factory()->create();
    $this->member = projectMemberWith($this->project, ['view_project_document', 'download_project_document']);

    $this->pdf = ProjectDocument::factory()->for($this->project)->create([
        'original_filename' => 'client-contract.pdf',
        'mime_type' => 'application/pdf',
        'file_path' => "project-documents/{$this->project->id}/abc.pdf",
    ]);
    Storage::disk('private')->put($this->pdf->file_path, '%PDF-1.7 body');
});

$viewUrl = fn (ProjectDocument $d) => "/admin/projects/{$d->project_id}/documents/{$d->id}/view";
$downloadUrl = fn (ProjectDocument $d) => "/admin/projects/{$d->project_id}/documents/{$d->id}/download";

it('streams a PDF inline for an authorized member', function () use ($viewUrl) {
    $res = $this->actingAs($this->member)->get($viewUrl($this->pdf));

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('inline');
});

it('downloads a PDF as an attachment with the original filename', function () use ($downloadUrl) {
    $res = $this->actingAs($this->member)->get($downloadUrl($this->pdf));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->headers->get('content-disposition'))->toContain('client-contract.pdf');
});

it('forces office files to download even on the view route (no inline preview)', function () use ($viewUrl) {
    $docx = ProjectDocument::factory()->for($this->project)->create([
        'original_filename' => 'statement.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'file_path' => "project-documents/{$this->project->id}/def.docx",
    ]);
    Storage::disk('private')->put($docx->file_path, "PK\x03\x04 body");

    $res = $this->actingAs($this->member)->get($viewUrl($docx));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
});

it('returns 404 when the stored file is missing', function () use ($viewUrl) {
    Storage::disk('private')->delete($this->pdf->file_path);

    $this->actingAs($this->member)->get($viewUrl($this->pdf))->assertNotFound();
});

it('returns 404 when the document does not belong to the route project (IDOR)', function () {
    $otherProject = Project::factory()->create();

    $this->actingAs($this->member)
        ->get("/admin/projects/{$otherProject->id}/documents/{$this->pdf->id}/view")
        ->assertNotFound();
});

it('forbids an authenticated member without the document permission', function () use ($viewUrl) {
    $stranger = projectMemberWith($this->project, []);

    $this->actingAs($stranger)->get($viewUrl($this->pdf))->assertForbidden();
});

it('redirects an unauthenticated visitor to login (auth middleware)', function () use ($viewUrl) {
    $this->get($viewUrl($this->pdf))->assertRedirectToRoute('login');
});
