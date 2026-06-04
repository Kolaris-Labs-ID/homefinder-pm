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

it('stores the CV on the private disk under the job-scoped path', function () {
    $file = UploadedFile::fake()->createWithContent('john-cv.pdf', '%PDF-1.4 content');

    $meta = $this->service->store($file, $this->job);

    expect($meta['resume_disk'])->toBe('private');
    expect($meta['resume_file_path'])->toStartWith("recruitment/resumes/{$this->job->id}/");
    expect($meta['resume_file_path'])->toEndWith('.pdf');
});

it('uses a random filename, not the original', function () {
    $file = UploadedFile::fake()->createWithContent('john-cv.pdf', '%PDF-1.4 content');

    $meta = $this->service->store($file, $this->job);

    expect($meta['resume_file_path'])->not->toContain('john-cv');
    expect($meta['resume_original_filename'])->toBe('john-cv.pdf');
});

it('records a sha256 hash of the file', function () {
    $content = '%PDF-1.4 deterministic content';
    $file = UploadedFile::fake()->createWithContent('cv.pdf', $content);

    $meta = $this->service->store($file, $this->job);

    expect($meta['resume_file_hash'])->toBe(hash('sha256', $content));
});

it('does not store the CV on the public disk', function () {
    Storage::fake('public');
    $file = UploadedFile::fake()->createWithContent('cv.pdf', '%PDF-1.4 content');

    $meta = $this->service->store($file, $this->job);

    Storage::disk('public')->assertMissing($meta['resume_file_path']);
});

it('can delete a stored CV (cleanup on partial failure)', function () {
    $file = UploadedFile::fake()->createWithContent('cv.pdf', '%PDF-1.4 content');
    $meta = $this->service->store($file, $this->job);

    $this->service->delete($meta['resume_disk'], $meta['resume_file_path']);

    Storage::disk('private')->assertMissing($meta['resume_file_path']);
});
