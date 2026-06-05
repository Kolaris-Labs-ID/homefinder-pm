<?php

use App\Services\Hris\Screening\Dto\CriteriaSnapshotDto;
use App\Services\Hris\Screening\Dto\FitOutcomeDto;
use App\Services\Hris\Screening\Dto\FlagDto;
use App\Services\Hris\Screening\Dto\GroupScoreDto;
use App\Services\Hris\Screening\Dto\MatchedCriterionDto;
use App\Services\Hris\Screening\Dto\MissingCriterionDto;
use App\Services\Hris\Screening\Dto\ScoringBreakdownDto;

it('stamps a schema_version on a flag and omits empty context', function () {
    $arr = (new FlagDto('recommendation_capped_missing_must_have', 'warning', 'Missing required criterion: PHP'))->toArray();

    expect($arr['schema_version'])->toBe('1')
        ->and($arr['code'])->toBe('recommendation_capped_missing_must_have')
        ->and($arr['severity'])->toBe('warning')
        ->and($arr['message'])->toBe('Missing required criterion: PHP')
        ->and($arr)->not->toHaveKey('context');
});

it('includes flag context when provided', function () {
    $arr = (new FlagDto('knockout_failed', 'critical', 'x', ['criterion_id' => 3]))->toArray();
    expect($arr['context'])->toBe(['criterion_id' => 3]);
});

it('serializes a matched criterion with terms and provenance', function () {
    $arr = (new MatchedCriterionDto(5, 'must_have', 'PHP', 2, ['php'], 'keyword'))->toArray();

    expect($arr)->toMatchArray([
        'schema_version' => '1',
        'criterion_id' => 5,
        'group' => 'must_have',
        'label' => 'PHP',
        'weight' => 2,
        'matched_terms' => ['php'],
        'via' => 'keyword',
    ]);
});

it('serializes a missing criterion', function () {
    $arr = (new MissingCriterionDto(7, 'nice_to_have', 'Docker', 1))->toArray();

    expect($arr)->toMatchArray([
        'schema_version' => '1', 'criterion_id' => 7, 'group' => 'nice_to_have', 'label' => 'Docker', 'weight' => 1,
    ]);
});

it('serializes a fit outcome with detail and omits null detail', function () {
    $withDetail = (new FitOutcomeDto('salary', 'failed', 'expected 20M > max 15M'))->toArray();
    expect($withDetail)->toMatchArray([
        'schema_version' => '1', 'kind' => 'salary', 'outcome' => 'failed', 'detail' => 'expected 20M > max 15M',
    ]);

    $noDetail = (new FitOutcomeDto('availability', 'unknown'))->toArray();
    expect($noDetail)->not->toHaveKey('detail');
});

it('serializes a scoring breakdown with per-group detail and totals', function () {
    $dto = new ScoringBreakdownDto([
        'must_have' => new GroupScoreDto(60, 2, 2, false, 60.0),
        'nice_to_have' => new GroupScoreDto(25, 0, 0, true, 25.0),
        'fit' => new GroupScoreDto(15, 1, 1, false, 15.0),
    ], 100.0, 100.00);

    $arr = $dto->toArray();

    expect($arr['schema_version'])->toBe('1')
        ->and($arr['total'])->toBe(100.0)
        ->and($arr['total_before_round'])->toBe(100.0)
        ->and($arr['groups']['nice_to_have']['empty'])->toBeTrue()
        ->and($arr['groups']['must_have']['score'])->toBe(60.0)
        ->and($arr['groups']['fit']['matched_weight'])->toBe(1);
});

it('serializes a criteria snapshot with the synonym dictionary version', function () {
    $arr = (new CriteriaSnapshotDto([
        ['id' => 1, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php'], 'synonyms' => [], 'weight' => 1, 'missing_means_failed' => false, 'is_active' => true, 'sort_order' => 0],
    ], '1'))->toArray();

    expect($arr['schema_version'])->toBe('1')
        ->and($arr['synonym_dictionary_version'])->toBe('1')
        ->and($arr['criteria'][0]['label'])->toBe('PHP');
});
