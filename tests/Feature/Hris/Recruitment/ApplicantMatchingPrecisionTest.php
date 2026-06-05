<?php

use App\Services\Hris\Screening\RequirementMatcher;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('does not match Java inside JavaScript unless JavaScript is explicitly configured', function () {
    $matcher = new RequirementMatcher;
    $java = [Ats::criterion(1, 'must_have', 'Java', ['java'])];
    $javascript = [Ats::criterion(1, 'must_have', 'JavaScript', ['javascript'])];

    $javaResult = $matcher->match('Built JavaScript applications.', $java);
    $javascriptResult = $matcher->match('Built JavaScript applications.', $javascript);

    expect(collect($javaResult->missing)->pluck('label'))->toContain('Java')
        ->and(collect($javascriptResult->matched)->pluck('label'))->toContain('JavaScript');
});

it('does not satisfy D3/S1 Accounting with SMK Akuntansi alone', function () {
    $result = (new RequirementMatcher)->match(
        'Synthetic CV: SMK Akuntansi graduate.',
        [Ats::criterion(1, 'must_have', 'D3/S1 Accounting', ['d3/s1 accounting'])],
    );

    expect(collect($result->missing)->pluck('label'))->toContain('D3/S1 Accounting');
});

it('handles implemented Indonesian and English negation patterns', function () {
    $result = (new RequirementMatcher)->match(
        Ats::fixtureText('negation_accounting_cv'),
        [
            Ats::criterion(1, 'must_have', 'Accounting', ['accounting']),
            Ats::criterion(2, 'must_have', 'Tax', ['pajak']),
        ],
    );

    expect(collect($result->missing)->pluck('label'))
        ->toContain('Accounting')
        ->toContain('Tax');
});

it('does not over-match every invoice mention as vendor invoice processing', function () {
    $result = (new RequirementMatcher)->match(
        'Handled customer invoice questions and receipt filing.',
        [Ats::criterion(1, 'must_have', 'Vendor invoice processing', ['vendor invoice processing'])],
    );

    expect(collect($result->missing)->pluck('label'))->toContain('Vendor invoice processing');
});

it('respects word boundaries for short keywords', function () {
    $result = (new RequirementMatcher)->match(
        'The text mentions syntax but not the standalone target term.',
        [Ats::criterion(1, 'must_have', 'Tax', ['tax'])],
    );

    expect(collect($result->missing)->pluck('label'))->toContain('Tax');
});
