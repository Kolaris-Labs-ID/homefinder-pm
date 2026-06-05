<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('PHP Laravel developer with MySQL experience.'));

    $this->job = JobPosting::factory()->create(['hiring_company_id' => HiringCompany::factory()->create()->id]);
    ApplicantScreeningCriteria::create(['job_posting_id' => $this->job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);

    $content = '%PDF-1.4 fake-cv';
    $path = "recruitment/resumes/{$this->job->id}/cv.pdf";
    Storage::disk('private')->put($path, $content);

    $this->applicant = Applicant::factory()->forJobPosting($this->job)->create([
        'resume_disk' => 'private',
        'resume_file_path' => $path,
        'resume_file_hash' => hash('sha256', $content),
    ]);
});

it('creates version 1 with a latest pointer on first screen', function () {
    $result = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    expect($result->version)->toBe(1)
        ->and($result->parser_status)->toBe('parsed')
        ->and($result->recommendation)->toBe('strong_match');

    $pointer = ApplicantScreeningLatestResult::where('applicant_id', $this->applicant->id)
        ->where('job_posting_id', $this->job->id)->first();

    expect($pointer->latest_result_id)->toBe($result->id)
        ->and($pointer->latest_version)->toBe(1);
});

it('creates a new version on manual re-run, advances the pointer, and leaves v1 immutable', function () {
    $orchestrator = app(ScreeningOrchestrator::class);

    $first = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid());
    $second = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid(), manual: true);

    expect($second->version)->toBe(2)
        ->and($second->id)->not->toBe($first->id);

    $pointers = ApplicantScreeningLatestResult::where('applicant_id', $this->applicant->id)
        ->where('job_posting_id', $this->job->id);

    expect($pointers->count())->toBe(1)
        ->and($pointers->first()->latest_result_id)->toBe($second->id)
        ->and($pointers->first()->latest_version)->toBe(2)
        ->and($first->fresh()->version)->toBe(1); // v1 row untouched
});

it('records a parser failure as needs_manual_review with no score', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::failing('pdf_text_empty'));

    $result = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    expect($result->parser_status)->toBe('failed')
        ->and($result->score)->toBeNull()
        ->and($result->recommendation)->toBe('needs_manual_review')
        ->and($result->parser_failure_reason)->toBe('pdf_text_empty');

    // a failed result still gets a frozen criteria snapshot + latest pointer
    expect(ApplicantScreeningResult::find($result->id)->criteria_snapshot)->not->toBeEmpty();
});
