<?php

use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\RelationManagers\DocumentsRelationManager;
use App\Models\Project;
use App\Models\ProjectDocument;
use Database\Seeders\ProjectDocumentPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('private');
    $this->seed(ProjectDocumentPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->project = Project::factory()->create();
    $this->admin = projectMemberWith($this->project, [
        'view_project_document', 'create_project_document', 'download_project_document', 'delete_project_document',
    ]);
    $this->actingAs($this->admin);
});

function documentsRM(Project $project)
{
    return Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);
}

it('renders the documents relation manager for an authorized user', function () {
    documentsRM($this->project)->assertSuccessful();
});

it('uploads a document through the relation manager via the storage service', function () {
    documentsRM($this->project)
        ->mountTableAction('create')
        ->setTableActionData([
            'upload' => UploadedFile::fake()->createWithContent('client-contract.pdf', '%PDF-1.7 body'),
            'category' => 'contract',
            'description' => 'signed copy',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $doc = ProjectDocument::where('project_id', $this->project->id)->first();
    expect($doc)->not->toBeNull()
        ->and($doc->original_filename)->toBe('client-contract.pdf')
        ->and($doc->category)->toBe('contract')
        ->and($doc->uploaded_by)->toBe($this->admin->id)
        ->and($doc->disk)->toBe('private');
    Storage::disk('private')->assertExists($doc->file_path);
});

it('requires a category on upload', function () {
    documentsRM($this->project)
        ->mountTableAction('create')
        ->setTableActionData([
            'upload' => UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.7'),
            'category' => null,
        ])
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['category']);
});

it('lists existing documents in the table', function () {
    $doc = ProjectDocument::factory()->for($this->project)->create(['original_filename' => 'brief.pdf', 'category' => 'brief']);

    documentsRM($this->project)->assertCanSeeTableRecords([$doc]);
});

it('archives a document via the table action', function () {
    $doc = ProjectDocument::factory()->for($this->project)->create();

    documentsRM($this->project)
        ->callTableAction('archive', $doc)
        ->assertHasNoTableActionErrors();

    expect($doc->fresh()->is_archived)->toBeTrue();
});

it('hides the upload action from a member without create permission', function () {
    $this->actingAs(projectMemberWith($this->project, ['view_project_document']));

    documentsRM($this->project)->assertTableActionHidden('create');
});
