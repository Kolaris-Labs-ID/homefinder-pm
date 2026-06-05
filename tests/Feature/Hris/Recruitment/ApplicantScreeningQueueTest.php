<?php

use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->withHeaders(['Accept' => 'application/json']);
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
});

it('public application submission dispatches screening without running PDF parsing inline', function () {
    Queue::fake();

    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'resume' => Ats::pdfUpload(),
    ]))->assertCreated();

    Queue::assertPushed(ScreenApplicantJob::class, function (ScreenApplicantJob $job) {
        return $job->queue === 'screening';
    });
    expect(ApplicantScreeningResult::count())->toBe(0);
});

it('failed parsing records a failure result and leaves applicant status unchanged', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::failing('pdf_text_empty'));
    $applicant = Ats::storedResumeApplicant($this->job);

    (new ScreenApplicantJob($applicant->id, $this->job->id, (string) Str::ulid()))
        ->handle(app(ScreeningOrchestrator::class));

    $result = ApplicantScreeningResult::where('applicant_id', $applicant->id)->firstOrFail();

    expect($result->parser_status)->toBe(ApplicantScreeningResult::PARSER_FAILED)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW)
        ->and($applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
});

it('retrying the same job is idempotent', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    $applicant = Ats::storedResumeApplicant($this->job);
    $job = new ScreenApplicantJob($applicant->id, $this->job->id, (string) Str::ulid());

    $job->handle(app(ScreeningOrchestrator::class));
    $job->handle(app(ScreeningOrchestrator::class));

    expect(ApplicantScreeningResult::where('applicant_id', $applicant->id)->count())->toBe(1);
});
