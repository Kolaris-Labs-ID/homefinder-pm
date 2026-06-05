<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
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
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('PHP developer.'));

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

it('is idempotent for the same screening_run_id (retry creates no new version)', function () {
    $orchestrator = app(ScreeningOrchestrator::class);
    $runId = (string) Str::ulid();

    $first = $orchestrator->screen($this->applicant, $this->job, $runId);
    $second = $orchestrator->screen($this->applicant, $this->job, $runId); // retry

    expect($second->id)->toBe($first->id)
        ->and(ApplicantScreeningResult::where('applicant_id', $this->applicant->id)
            ->where('job_posting_id', $this->job->id)->count())->toBe(1);
});

it('holds a legacy applicant without a resume hash for manual review (hash_missing)', function () {
    $this->applicant->update(['resume_file_hash' => null]);

    $result = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    expect($result->parser_status)->toBe('failed')
        ->and($result->parser_failure_reason)->toBe('hash_missing')
        ->and(collect($result->flags)->pluck('code'))->toContain('hash_missing');
});
