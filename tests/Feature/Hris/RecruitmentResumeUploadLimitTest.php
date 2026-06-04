<?php

use App\Models\Hris\JobPosting;
use App\Services\Hris\ResumeStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->service = new ResumeStorageService;
    $this->job = JobPosting::factory()->create();
});

it('accepts a valid PDF under 1 MB', function () {
    $file = UploadedFile::fake()->createWithContent('cv.pdf', '%PDF-1.4 fake content');

    $meta = $this->service->store($file, $this->job);

    expect($meta['resume_mime_type'])->toBe('application/pdf');
    expect($meta['resume_size_bytes'])->toBeLessThanOrEqual(1048576);
    Storage::disk('private')->assertExists($meta['resume_file_path']);
});

it('rejects a PDF larger than 1 MB', function () {
    // 2 MB of %PDF-prefixed content
    $file = UploadedFile::fake()->createWithContent('big.pdf', '%PDF'.str_repeat('a', 2 * 1024 * 1024));

    $this->service->store($file, $this->job);
})->throws(RuntimeException::class);

it('rejects a DOCX file', function () {
    $file = UploadedFile::fake()->createWithContent('cv.docx', 'PK'.str_repeat('x', 100));

    $this->service->store($file, $this->job);
})->throws(RuntimeException::class);

it('rejects a non-PDF file disguised with a .pdf extension', function () {
    $file = UploadedFile::fake()->createWithContent('fake.pdf', 'this is plain text, not a pdf');

    $this->service->store($file, $this->job);
})->throws(RuntimeException::class);
