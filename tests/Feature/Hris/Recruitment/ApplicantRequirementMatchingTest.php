<?php

use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\RequirementMatcher;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('scores a strong accounting CV as strong_match', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('accounting_strong_match'),
        Ats::accountingCriteria(),
    );

    expect($result->score)->toBeGreaterThanOrEqual(80.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH);
});

it('scores a partial accounting CV in review range', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('accounting_partial_match'),
        Ats::accountingCriteria(),
    );

    expect($result->score)->toBeGreaterThanOrEqual(50.0)
        ->and($result->score)->toBeLessThan(80.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_REVIEW);
});

it('scores a low-match CV below 50', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('accounting_low_match'),
        Ats::accountingCriteria(),
    );

    expect($result->score)->toBeLessThan(50.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_LOW_MATCH);
});

it('scores a software developer CV against an accounting job as low_match', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('software_developer_strong_match'),
        Ats::accountingCriteria(),
    );

    expect($result->score)->toBeLessThan(50.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_LOW_MATCH);
});

it('treats score 80 exactly as strong_match only when all must-haves match', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 1),
        Ats::criterion(2, 'nice_to_have', 'Vendor invoice', ['vendor invoice'], 4),
        Ats::criterion(3, 'nice_to_have', 'Missing nice', ['not-present'], 1),
        Ats::criterion(4, 'fit', 'Missing fit', ['not-present'], 1),
    ];

    $result = (new RequirementMatcher)->match('accounting vendor invoice', $criteria);

    expect($result->score)->toBe(80.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH);
});

it('treats score 50 exactly as review', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 5),
        Ats::criterion(2, 'must_have', 'Missing must-have', ['not-present'], 1),
        Ats::criterion(3, 'nice_to_have', 'Missing nice', ['not-present'], 1),
        Ats::criterion(4, 'fit', 'Missing fit', ['not-present'], 1),
    ];

    $result = (new RequirementMatcher)->match('accounting', $criteria);

    expect($result->score)->toBe(50.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_REVIEW);
});

it('treats score 49 exactly as low_match', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 49),
        Ats::criterion(2, 'must_have', 'Missing must-have', ['not-present'], 11),
        Ats::criterion(3, 'nice_to_have', 'Missing nice', ['not-present'], 1),
        Ats::criterion(4, 'fit', 'Missing fit', ['not-present'], 1),
    ];

    $result = (new RequirementMatcher)->match('accounting', $criteria);

    expect($result->score)->toBe(49.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_LOW_MATCH);
});

it('caps a high score at review when a must-have is missing', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 46),
        Ats::criterion(2, 'must_have', 'D3/S1 Accounting', ['d3/s1 accounting'], 14),
        Ats::criterion(3, 'nice_to_have', 'Bookkeeping', ['bookkeeping'], 1),
        Ats::criterion(4, 'fit', 'Jakarta', ['jakarta'], 1),
    ];

    $result = (new RequirementMatcher)->match('accounting bookkeeping jakarta', $criteria);

    expect($result->score)->toBe(86.0)
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_REVIEW);
});

it('failed knockout caps recommendation at needs_manual_review', function () {
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting'], 1),
        Ats::criterion(2, 'nice_to_have', 'Bookkeeping', ['bookkeeping'], 1),
        Ats::criterion(3, 'fit', 'Jakarta', ['jakarta'], 1),
        Ats::criterion(4, 'knockout', 'Work authorization', ['work authorization'], 1, [
            'missing_means_failed' => true,
        ]),
    ];

    $result = (new RequirementMatcher)->match('accounting bookkeeping jakarta', $criteria);

    expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW)
        ->and(collect($result->flags)->pluck('code'))->toContain('knockout_failed');
});
