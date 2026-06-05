<?php

use App\Models\Hris\ApplicantScreeningOverride;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\User;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
    $this->applicant = Ats::storedResumeApplicant($this->job);
    $this->user = User::factory()->create();
});

it('overrides strong_match to review without changing the raw score', function () {
    $result = app(ScreeningOrchestrator::class)->screen($this->applicant, $this->job, (string) Str::ulid());

    app(ScreeningOrchestrator::class)->override($this->applicant, ApplicantScreeningResult::REC_REVIEW, 'HR wants a second look.', $this->user);

    expect($result->fresh()->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH)
        ->and($result->fresh()->score)->toBe('100.00')
        ->and($this->applicant->fresh()->effectiveScreeningRecommendation())->toBe(ApplicantScreeningResult::REC_REVIEW);
});

it('overrides low_match to review and audits the reason and actor', function () {
    app(ScreeningOrchestrator::class)->override($this->applicant, ApplicantScreeningResult::REC_REVIEW, 'Manual evidence supplied.', $this->user);

    $override = ApplicantScreeningOverride::where('applicant_id', $this->applicant->id)->firstOrFail();

    expect($override->overridden_recommendation)->toBe(ApplicantScreeningResult::REC_REVIEW)
        ->and($override->override_note)->toBe('Manual evidence supplied.')
        ->and($override->created_by)->toBe($this->user->id)
        ->and($override->is_active)->toBeTrue();
});

it('preserves an active override across re-screening until HR clears it', function () {
    $orchestrator = app(ScreeningOrchestrator::class);
    $orchestrator->override($this->applicant, ApplicantScreeningResult::REC_REVIEW, 'Preserve this.', $this->user);

    $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid());
    $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid(), manual: true);

    expect($this->applicant->fresh()->activeScreeningOverride)->not->toBeNull()
        ->and($this->applicant->fresh()->effectiveScreeningRecommendation())->toBe(ApplicantScreeningResult::REC_REVIEW);
});
