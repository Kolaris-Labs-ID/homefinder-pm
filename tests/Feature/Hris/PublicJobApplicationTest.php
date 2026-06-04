<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->withHeaders(['Accept' => 'application/json']); // clients (Next proxy) request JSON
    $this->job = JobPosting::factory()->publicPortal()->create([
        'slug' => 'backend-engineer',
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
});

function applyPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Jane Candidate',
        'email' => 'jane@example.com',
        'phone' => '08123456789',
        'cover_letter' => 'I would love to join.',
        'consent_privacy_policy' => true,
        'consent_data_processing' => true,
        'website' => '', // honeypot
    ], $overrides);
}

function pdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('resume.pdf', '%PDF-1.4 content');
}

it('accepts a valid application and returns a reference code', function () {
    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => pdf()]));

    $res->assertCreated();
    expect($res->json('data.application_reference_code'))->not->toBeEmpty();

    $applicant = Applicant::first();
    expect($applicant->source)->toBe(Applicant::SOURCE_JOB_PORTAL);
    expect($applicant->status)->toBe(Applicant::STATUS_APPLIED);
    expect($applicant->consent_privacy_policy)->toBeTrue();
    expect($applicant->consent_accepted_at)->not->toBeNull();
    expect($applicant->retention_until)->not->toBeNull();
    expect($applicant->applied_ip)->not->toBeNull();
    Storage::disk('private')->assertExists($applicant->resume_file_path);
});

it('blocks a duplicate application with a friendly message and leaves no orphan file', function () {
    $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => pdf()]))->assertCreated();

    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['email' => 'JANE@example.com', 'resume' => pdf()]));

    $res->assertStatus(422);
    expect($res->json('message'))->toContain('already applied');
    expect(Applicant::count())->toBe(1);
    // No orphaned CV: only the first applicant's file exists.
    expect(Storage::disk('private')->allFiles())->toHaveCount(1);
});

it('allows the same email to apply to a different job', function () {
    $other = JobPosting::factory()->publicPortal()->create(['slug' => 'frontend-engineer']);

    $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => pdf()]))->assertCreated();
    $this->post('/api/public/v1/jobs/frontend-engineer/apply', applyPayload(['resume' => pdf()]))->assertCreated();

    expect(Applicant::count())->toBe(2);
});

it('requires both consent checkboxes', function () {
    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['consent_privacy_policy' => false, 'resume' => pdf()]));
    $res->assertStatus(422);
    expect(Applicant::count())->toBe(0);
});

it('rejects when the honeypot is filled', function () {
    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['website' => 'http://spam.test', 'resume' => pdf()]));
    $res->assertStatus(422);
    expect(Applicant::count())->toBe(0);
});

it('rejects a non-PDF resume', function () {
    $bad = UploadedFile::fake()->createWithContent('resume.docx', 'PKnot-a-pdf');
    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => $bad]));
    $res->assertStatus(422);
    expect(Applicant::count())->toBe(0);
});

it('rejects a resume larger than 1 MB', function () {
    $big = UploadedFile::fake()->create('resume.pdf', 2048, 'application/pdf'); // 2 MB
    $res = $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => $big]));
    $res->assertStatus(422);
    expect(Applicant::count())->toBe(0);
});

it('returns 404 when applying to a non-public job', function () {
    JobPosting::factory()->draft()->create(['slug' => 'secret-role']);
    $res = $this->post('/api/public/v1/jobs/secret-role/apply', applyPayload(['resume' => pdf()]));
    $res->assertNotFound();
});

it('stores consent policy version', function () {
    $this->post('/api/public/v1/jobs/backend-engineer/apply', applyPayload(['resume' => pdf()]))->assertCreated();
    expect(Applicant::first()->consent_policy_version)->not->toBeNull();
});
