<?php

use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
    $this->applicant = Ats::storedResumeApplicant($this->job, ['status' => Applicant::STATUS_APPLIED]);
});

it('serializes retry behavior through screening_run_id idempotency', function () {
    $runId = (string) Str::ulid();
    $orchestrator = app(ScreeningOrchestrator::class);

    $first = $orchestrator->screen($this->applicant, $this->job, $runId);
    $retry = $orchestrator->screen($this->applicant, $this->job, $runId);

    expect($retry->id)->toBe($first->id)
        ->and(ApplicantScreeningResult::where('applicant_id', $this->applicant->id)->count())->toBe(1)
        ->and(ApplicantScreeningLatestResult::where('applicant_id', $this->applicant->id)->count())->toBe(1);
});

it('prevents duplicate version collisions at the database layer', function () {
    $first = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    $attributes = $first->getAttributes();
    unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
    $attributes['screening_run_id'] = (string) Str::ulid();

    ApplicantScreeningResult::create($attributes);
})->throws(QueryException::class);

it('revalidates file hash at job execution and records safe failure if the file changed', function () {
    Storage::disk('private')->put($this->applicant->resume_file_path, '%PDF-1.4 changed content');

    (new ScreenApplicantJob($this->applicant->id, $this->job->id, (string) Str::ulid()))
        ->handle(app(ScreeningOrchestrator::class));

    $result = ApplicantScreeningResult::where('applicant_id', $this->applicant->id)->firstOrFail();

    expect($result->parser_status)->toBe(ApplicantScreeningResult::PARSER_FAILED)
        ->and($result->parser_failure_reason)->toBe('resume_hash_mismatch')
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW)
        ->and($this->applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
});
