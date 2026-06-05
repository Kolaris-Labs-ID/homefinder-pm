<?php

use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\RequirementMatcher;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('flags abnormal repeated job keywords', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('keyword_stuffed_cv'),
        [
            Ats::criterion(1, 'must_have', 'Accounting', ['accounting']),
            Ats::criterion(2, 'must_have', 'Tax', ['tax']),
        ],
    );

    expect(collect($result->flags)->pluck('code'))->toContain('abnormal_keyword_density')
        ->and($result->recommendation)->not->toBe(ApplicantScreeningResult::REC_STRONG_MATCH);
});

it('caps warning-level keyword stuffing at review', function () {
    $cvText = str_repeat('accounting ', 8).str_repeat('neutral filler token ', 80);

    $result = (new RequirementMatcher)->match(
        $cvText,
        [Ats::criterion(1, 'must_have', 'Accounting', ['accounting'])],
    );

    expect(collect($result->flags)->pluck('code'))->toContain('abnormal_keyword_density')
        ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_REVIEW);
});

it('critical keyword stuffing forces manual review', function () {
    $cvText = str_repeat('accounting ', 20).str_repeat('neutral filler token ', 10);

    $result = (new RequirementMatcher)->match(
        $cvText,
        [Ats::criterion(1, 'must_have', 'Accounting', ['accounting'])],
    );

    expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW);
});
