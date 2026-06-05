<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting tax bookkeeping jakarta'));
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
    Ats::createCriterion($this->job, 'nice_to_have', 'Bookkeeping', ['bookkeeping']);
    Ats::createCriterion($this->job, 'fit', 'Jakarta', ['jakarta']);
    $this->applicant = Ats::storedResumeApplicant($this->job, ['status' => Applicant::STATUS_APPLIED]);
});

it('creates version 1 then version 2 without overwriting the old result', function () {
    $orchestrator = app(ScreeningOrchestrator::class);

    $first = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid());
    $second = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid(), manual: true);

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($first->fresh()->id)->toBe($first->id)
        ->and($first->fresh()->version)->toBe(1)
        ->and(ApplicantScreeningResult::where('applicant_id', $this->applicant->id)->count())->toBe(2);

    $latest = ApplicantScreeningLatestResult::where('applicant_id', $this->applicant->id)->firstOrFail();
    expect($latest->latest_result_id)->toBe($second->id)
        ->and($latest->latest_version)->toBe(2);
});

it('creates a new version when criteria change and stores component versions', function () {
    $orchestrator = app(ScreeningOrchestrator::class);

    $first = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid());
    Ats::createCriterion($this->job, 'must_have', 'Tax', ['tax']);
    $second = $orchestrator->screen($this->applicant, $this->job, (string) Str::ulid(), manual: true);

    expect($second->version)->toBe(2)
        ->and($second->criteria_snapshot_hash)->not->toBe($first->criteria_snapshot_hash)
        ->and($second->parser_version)->toBe((string) config('screening.versions.parser'))
        ->and($second->matcher_version)->toBe((string) config('screening.versions.matcher'))
        ->and($second->screening_version)->toBe((string) config('screening.versions.screening'));
});
