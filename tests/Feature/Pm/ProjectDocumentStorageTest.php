<?php

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\ProjectDocumentStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->service = app(ProjectDocumentStorageService::class);
    $this->project = Project::factory()->create();
    $this->user = User::factory()->create();
});

function storeDoc(ProjectDocumentStorageService $service, Project $project, User $user, UploadedFile $file, string $category = 'contract', ?string $description = null): ProjectDocument
{
    return $service->store($project, $file, $category, $description, $user);
}

it('stores a valid PDF on the private disk with metadata', function () {
    $body = '%PDF-1.7 body';
    $doc = storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('client-contract.pdf', $body), 'contract');

    expect($doc->disk)->toBe('private')
        ->and($doc->original_filename)->toBe('client-contract.pdf')
        ->and($doc->mime_type)->toContain('pdf')
        ->and($doc->category)->toBe('contract')
        ->and($doc->uploaded_by)->toBe($this->user->id)
        ->and($doc->size_bytes)->toBe(strlen($body))
        ->and($doc->file_hash)->toBe(hash('sha256', $body));

    Storage::disk('private')->assertExists($doc->file_path);
});

it('randomizes the stored filename and scopes it under the project id', function () {
    $doc = storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('Secret Plan.pdf', '%PDF-1.7'));

    expect($doc->file_path)->toStartWith("project-documents/{$this->project->id}/")
        ->and($doc->file_path)->not->toContain('Secret Plan')
        ->and($doc->file_path)->toEndWith('.pdf');
});

it('stores a valid PNG image', function () {
    $doc = storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('logo.png', "\x89PNG\r\n\x1a\n".str_repeat('x', 32)), 'design');
    Storage::disk('private')->assertExists($doc->file_path);
});

it('stores a valid DOCX office document', function () {
    $doc = storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('statement.docx', "PK\x03\x04".str_repeat('x', 32)), 'sow');
    Storage::disk('private')->assertExists($doc->file_path);
});

it('rejects a file larger than 10 MB', function () {
    expect(fn () => storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->create('big.pdf', 11 * 1024)))
        ->toThrow(RuntimeException::class);
});

it('rejects a disallowed extension', function () {
    expect(fn () => storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('payload.exe', 'MZ')))
        ->toThrow(RuntimeException::class);
});

it('rejects a fake PDF whose bytes are not really a PDF and stores nothing', function () {
    expect(fn () => storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('fake.pdf', '<html>not a pdf</html>')))
        ->toThrow(RuntimeException::class);

    expect(ProjectDocument::count())->toBe(0);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

it('rejects a disallowed category', function () {
    expect(fn () => storeDoc($this->service, $this->project, $this->user, UploadedFile::fake()->createWithContent('x.pdf', '%PDF-1.7'), 'malware'))
        ->toThrow(RuntimeException::class);
});
