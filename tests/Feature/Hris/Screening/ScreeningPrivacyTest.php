<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningOverride;
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
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning(
        "PHP Laravel engineer.\nNIK: 3201234567890123\nAlamat: Jl. Merdeka, Jakarta\nSkills: PHP",
    ));

    $this->job = JobPosting::factory()->create(['hiring_company_id' => HiringCompany::factory()->create()->id]);
    ApplicantScreeningCriteria::create(['job_posting_id' => $this->job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);

    $content = '%PDF-1.4 cv';
    $path = "recruitment/resumes/{$this->job->id}/cv.pdf";
    Storage::disk('private')->put($path, $content);
    $this->applicant = Applicant::factory()->forJobPosting($this->job)->create([
        'resume_disk' => 'private',
        'resume_file_path' => $path,
        'resume_file_hash' => hash('sha256', $content),
    ]);
});

it('persists a redacted summary, never the raw NIK', function () {
    $result = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    expect($result->parser_status)->toBe('parsed')
        ->and($result->parsed_summary)->not->toBeNull()
        ->and($result->parsed_summary)->not->toContain('3201234567890123')
        ->and($result->parsed_summary)->not->toContain('Jl. Merdeka')
        ->and($result->redacted_fields)->toContain('nik');
});

it('forgetApplicant erases every screening artifact for the applicant', function () {
    app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());
    ApplicantScreeningOverride::create([
        'applicant_id' => $this->applicant->id,
        'job_posting_id' => $this->job->id,
        'overridden_recommendation' => 'strong_match',
        'override_note' => 'note',
    ]);

    app(ScreeningOrchestrator::class)->forgetApplicant($this->applicant);

    expect(ApplicantScreeningResult::where('applicant_id', $this->applicant->id)->count())->toBe(0)
        ->and(ApplicantScreeningLatestResult::where('applicant_id', $this->applicant->id)->count())->toBe(0)
        ->and(ApplicantScreeningOverride::where('applicant_id', $this->applicant->id)->count())->toBe(0);
});
