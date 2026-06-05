<?php

use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\RequirementMatcher;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('returns the same score for the same CV text, job criteria, and reference date', function () {
    $matcher = new RequirementMatcher;
    $criteria = Ats::accountingCriteria();
    $referenceDate = new DateTimeImmutable('2026-01-01');

    $first = $matcher->match(Ats::fixtureText('accounting_strong_match'), $criteria, $referenceDate);
    $second = $matcher->match(Ats::fixtureText('accounting_strong_match'), $criteria, $referenceDate);

    expect($first->score)->toBe($second->score)
        ->and($first->recommendation)->toBe($second->recommendation);
});

it('ignores whitespace, criteria order, and keyword order for scoring', function () {
    $matcher = new RequirementMatcher;

    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['tax', 'accounting']),
        Ats::criterion(2, 'nice_to_have', 'Bookkeeping', ['vendor invoice', 'bookkeeping']),
        Ats::criterion(3, 'fit', 'Jakarta', ['jakarta']),
    ];
    $reordered = array_reverse([
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting', 'tax']),
        Ats::criterion(2, 'nice_to_have', 'Bookkeeping', ['bookkeeping', 'vendor invoice']),
        Ats::criterion(3, 'fit', 'Jakarta', ['jakarta']),
    ]);

    $first = $matcher->match("accounting   tax\nbookkeeping vendor invoice jakarta", $criteria);
    $second = $matcher->match(' accounting tax bookkeeping vendor invoice jakarta ', $reordered);

    expect($first->score)->toBe($second->score)
        ->and($first->recommendation)->toBe($second->recommendation);
});

it('normalizes criteria counts using active group weights', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 3),
        Ats::criterion(2, 'must_have', 'Tax', ['tax'], 1),
        Ats::criterion(3, 'nice_to_have', 'Bookkeeping', ['bookkeeping'], 1),
        Ats::criterion(4, 'fit', 'Jakarta', ['jakarta'], 1),
    ];

    $result = (new RequirementMatcher)->match('accounting bookkeeping jakarta', $criteria);

    expect($result->breakdown->groups['must_have']->score)->toBe(45.0)
        ->and($result->score)->toBe(85.0);
});

it('stores parser, matcher, screening, synonym versions and a fixed reference date', function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting tax'));
    $job = Ats::publicJob();
    Ats::createCriterion($job, 'must_have', 'Accounting', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job, ['created_at' => '2026-01-01 09:00:00']);

    $result = app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    expect($result->parser_version)->toBe((string) config('screening.versions.parser'))
        ->and($result->matcher_version)->toBe((string) config('screening.versions.matcher'))
        ->and($result->screening_version)->toBe((string) config('screening.versions.screening'))
        ->and($result->synonym_dictionary_version)->toBe((string) config('screening.versions.synonym'))
        ->and($result->screening_reference_date->toDateString())->toBe('2026-01-01');
});
