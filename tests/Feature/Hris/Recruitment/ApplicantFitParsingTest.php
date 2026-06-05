<?php

use App\Services\Hris\Screening\Dto\FitOutcomeDto;
use App\Services\Hris\Screening\RequirementMatcher;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('records a matched salary fit when expected salary is within the visible max', function () {
    $result = (new RequirementMatcher)->match(
        'accounting',
        [Ats::criterion(1, 'must_have', 'Accounting', ['accounting'])],
        fitContext: ['expected_salary' => 10000000, 'salary_max' => 10000000, 'salary_hidden' => false],
    );

    $salary = collect($result->fitOutcomes)->firstWhere('kind', FitOutcomeDto::KIND_SALARY);

    expect($salary->outcome)->toBe(FitOutcomeDto::OUTCOME_MATCHED);
});

it('records a failed salary fit and flag when expected salary is above max', function () {
    $result = (new RequirementMatcher)->match(
        'accounting',
        [Ats::criterion(1, 'must_have', 'Accounting', ['accounting'])],
        fitContext: ['expected_salary' => 10000001, 'salary_max' => 10000000, 'salary_hidden' => false],
    );

    $salary = collect($result->fitOutcomes)->firstWhere('kind', FitOutcomeDto::KIND_SALARY);

    expect($salary->outcome)->toBe(FitOutcomeDto::OUTCOME_FAILED)
        ->and(collect($result->flags)->pluck('code'))->toContain('fit_salary_failed');
});

it('treats unknown salary fit as unknown without crashing matching', function () {
    $result = (new RequirementMatcher)->match(
        'accounting',
        [Ats::criterion(1, 'must_have', 'Accounting', ['accounting'])],
        fitContext: ['expected_salary' => null, 'salary_max' => 10000000, 'salary_hidden' => false],
    );

    $salary = collect($result->fitOutcomes)->firstWhere('kind', FitOutcomeDto::KIND_SALARY);

    expect($salary->outcome)->toBe(FitOutcomeDto::OUTCOME_UNKNOWN)
        ->and($result->score)->toBeGreaterThan(0.0);
});
