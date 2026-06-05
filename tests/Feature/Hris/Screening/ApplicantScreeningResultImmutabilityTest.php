<?php

use App\Exceptions\ImmutableScreeningResultException;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Support\Str;

function makeResult(array $overrides = []): ApplicantScreeningResult
{
    $job = JobPosting::factory()->create([
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
    $applicant = Applicant::factory()->forJobPosting($job)->create();

    return ApplicantScreeningResult::create(array_merge([
        'screening_run_id' => (string) Str::ulid(),
        'applicant_id' => $applicant->id,
        'job_posting_id' => $job->id,
        'hiring_company_id' => $job->hiring_company_id,
        'version' => 1,
        'parser_status' => 'parsed',
        'score' => 75.00,
        'recommendation' => 'review',
        'flags' => [],
        'matched_criteria' => [],
        'missing_criteria' => [],
        'fit_outcomes' => [],
        'raw_scoring_breakdown' => [],
        'criteria_snapshot' => [],
        'criteria_snapshot_hash' => str_repeat('0', 64),
        'parser_version' => '1',
        'matcher_version' => '1',
        'screening_version' => '1',
        'synonym_dictionary_version' => '1',
        'screening_reference_date' => $applicant->created_at->toDateString(),
    ], $overrides));
}

it('blocks updates to score', function () {
    $r = makeResult();
    $r->score = 90.00;
    expect(fn () => $r->save())->toThrow(ImmutableScreeningResultException::class);
});

it('blocks updates to recommendation', function () {
    $r = makeResult();
    $r->recommendation = 'strong_match';
    expect(fn () => $r->save())->toThrow(ImmutableScreeningResultException::class);
});

it('blocks updates to criteria_snapshot', function () {
    $r = makeResult();
    $r->criteria_snapshot = [['id' => 1]];
    expect(fn () => $r->save())->toThrow(ImmutableScreeningResultException::class);
});

it('blocks updates to flags', function () {
    $r = makeResult();
    $r->flags = [['code' => 'x']];
    expect(fn () => $r->save())->toThrow(ImmutableScreeningResultException::class);
});

it('casts json columns to arrays', function () {
    $r = makeResult(['flags' => [['code' => 'a']]]);
    expect($r->fresh()->flags)->toBe([['code' => 'a']]);
});
