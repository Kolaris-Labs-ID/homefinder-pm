<?php

use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\RequirementMatcher;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('matches accounting and akuntansi bidirectionally', function () {
    foreach ([['accounting', 'Pengalaman akuntansi dan rekonsiliasi.'], ['akuntansi', 'Accounting reconciliation experience.']] as [$criterion, $cvText]) {
        $result = (new RequirementMatcher)->match(
            $cvText,
            [Ats::criterion(1, 'must_have', 'Accounting synonym', [$criterion])],
        );

        expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH)
            ->and(collect($result->matched)->pluck('label'))->toContain('Accounting synonym');
    }
});

it('matches tax and pajak bidirectionally', function () {
    foreach ([['tax', 'Pengalaman pajak bulanan.'], ['pajak', 'Monthly tax reporting experience.']] as [$criterion, $cvText]) {
        $result = (new RequirementMatcher)->match(
            $cvText,
            [Ats::criterion(1, 'must_have', 'Tax synonym', [$criterion])],
        );

        expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH);
    }
});

it('matches frontend criteria from React and Next.js source terms', function () {
    foreach (['Built React applications.', 'Built Next.js applications.'] as $cvText) {
        $result = (new RequirementMatcher)->match(
            $cvText,
            [Ats::criterion(1, 'must_have', 'Frontend', ['frontend'])],
        );

        expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH)
            ->and(collect($result->matched)->first()->via)->toBe('synonym');
    }
});

it('matches PHP criteria from Laravel but does not make PHP imply Laravel', function () {
    $matcher = new RequirementMatcher;

    $phpFromLaravel = $matcher->match('Laravel service development.', [
        Ats::criterion(1, 'must_have', 'PHP', ['php']),
    ]);
    $laravelFromPhp = $matcher->match('PHP service development.', [
        Ats::criterion(1, 'must_have', 'Laravel', ['laravel']),
    ]);

    expect($phpFromLaravel->recommendation)->toBe(ApplicantScreeningResult::REC_STRONG_MATCH)
        ->and(collect($laravelFromPhp->missing)->pluck('label'))->toContain('Laravel');
});
