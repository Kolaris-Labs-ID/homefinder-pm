<?php

use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('private');
    $this->withHeaders(['Accept' => 'application/json']);
    $this->job = JobPosting::factory()->publicPortal()->create([
        'slug' => 'backend-engineer',
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
});

function screeningApplyPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Jane Candidate',
        'email' => 'jane@example.com',
        'phone' => '08123456789',
        'cover_letter' => 'Hello.',
        'consent_privacy_policy' => true,
        'consent_data_processing' => true,
        'website' => '',
    ], $overrides);
}

function screeningPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('resume.pdf', '%PDF-1.4 content');
}

it('dispatches the screening job after a successful public application', function () {
    Queue::fake();

    $this->post('/api/public/v1/jobs/backend-engineer/apply', screeningApplyPayload(['resume' => screeningPdf()]))
        ->assertCreated();

    Queue::assertPushed(ScreenApplicantJob::class, 1);
});

it('does not dispatch screening for an invalid (non-pdf) upload', function () {
    Queue::fake();

    $this->post('/api/public/v1/jobs/backend-engineer/apply', screeningApplyPayload([
        'resume' => UploadedFile::fake()->create('cv.docx', 10),
    ]))->assertStatus(422);

    Queue::assertNotPushed(ScreenApplicantJob::class);
});

it('does not dispatch a second screening for a duplicate application', function () {
    Queue::fake();

    $this->post('/api/public/v1/jobs/backend-engineer/apply', screeningApplyPayload(['email' => 'dupe@example.com', 'resume' => screeningPdf()]))
        ->assertCreated();
    Queue::assertPushed(ScreenApplicantJob::class, 1);

    $this->post('/api/public/v1/jobs/backend-engineer/apply', screeningApplyPayload(['email' => 'DUPE@example.com', 'resume' => screeningPdf()]))
        ->assertStatus(422);
    Queue::assertPushed(ScreenApplicantJob::class, 1); // unchanged — no second dispatch
});

it('screens the applicant when the job actually runs', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('PHP developer.'));
    ApplicantScreeningCriteria::create(['job_posting_id' => $this->job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);

    $content = '%PDF-1.4 fake-cv';
    $path = "recruitment/resumes/{$this->job->id}/cv.pdf";
    Storage::disk('private')->put($path, $content);
    $applicant = Applicant::factory()->forJobPosting($this->job)->create([
        'resume_disk' => 'private',
        'resume_file_path' => $path,
        'resume_file_hash' => hash('sha256', $content),
    ]);

    (new ScreenApplicantJob($applicant->id, $this->job->id, (string) Str::ulid()))
        ->handle(app(ScreeningOrchestrator::class));

    $result = ApplicantScreeningResult::where('applicant_id', $applicant->id)->first();
    expect($result)->not->toBeNull()
        ->and($result->parser_status)->toBe('parsed');
});
