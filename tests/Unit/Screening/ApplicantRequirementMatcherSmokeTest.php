<?php

use App\Services\Hris\Screening\RequirementMatcher;

function crit(int $id, string $group, array $keywords, int $weight = 1, bool $missingFails = false): array
{
    return [
        'id' => $id,
        'group' => $group,
        'label' => implode('/', $keywords),
        'keywords' => $keywords,
        'synonyms' => [],
        'weight' => $weight,
        'missing_means_failed' => $missingFails,
        'is_active' => true,
        'sort_order' => 0,
    ];
}

function flagCodes($result): array
{
    return array_map(fn ($f) => $f->code, $result->flags);
}

function matcher(): RequirementMatcher
{
    return new RequirementMatcher;
}

it('caps a high score to review when a must-have is missing, and records the cap flag', function () {
    $criteria = [
        crit(1, 'must_have', ['php']),
        crit(2, 'must_have', ['laravel']),
        crit(3, 'must_have', ['mysql']),
        crit(4, 'must_have', ['docker']),
    ];
    // matches 3 of 4 must-haves => must_have 60*3/4=45; nice empty 25; fit empty 15; total 85.
    $result = matcher()->match('Engineer skilled in PHP, Laravel and MySQL.', $criteria);

    expect(number_format($result->score, 2))->toBe('85.00')
        ->and($result->recommendation)->toBe('review')
        ->and(flagCodes($result))->toContain('recommendation_capped_missing_must_have');
});

it('does not cap the max score at 85 when the fit group is empty', function () {
    $criteria = [
        crit(1, 'must_have', ['php']),
        crit(2, 'nice_to_have', ['docker']),
    ];
    // must_have 60; nice 25; fit empty grants full 15 => 100 (not 85).
    $result = matcher()->match('PHP and Docker expert.', $criteria);

    expect(number_format($result->score, 2))->toBe('100.00')
        ->and($result->recommendation)->toBe('strong_match')
        ->and(flagCodes($result))->toContain('criteria_group_empty:fit');
});

it('treats an unknown knockout differently from a failed knockout', function () {
    $unknown = matcher()->match('PHP developer.', [
        crit(1, 'must_have', ['php']),
        crit(9, 'knockout', ['sim a'], missingFails: false),
    ]);
    expect($unknown->recommendation)->not->toBe('needs_manual_review')
        ->and(flagCodes($unknown))->toContain('knockout_unknown');

    $failed = matcher()->match('PHP developer.', [
        crit(1, 'must_have', ['php']),
        crit(9, 'knockout', ['sim a'], missingFails: true),
    ]);
    expect($failed->recommendation)->toBe('needs_manual_review')
        ->and(flagCodes($failed))->toContain('knockout_failed');
});

it('produces the same score regardless of criteria order and whitespace', function () {
    $ordered = [
        crit(1, 'must_have', ['php']),
        crit(2, 'nice_to_have', ['docker']),
        crit(3, 'fit', ['jakarta']),
    ];
    $shuffled = [
        crit(3, 'fit', ['jakarta']),
        crit(1, 'must_have', ['php']),
        crit(2, 'nice_to_have', ['docker']),
    ];

    $a = matcher()->match('PHP Docker Jakarta', $ordered);
    $b = matcher()->match("  PHP   \n\t Docker     Jakarta  ", $shuffled);

    expect($a->score)->toBe($b->score);
});

it('rounds the final score half-up to two decimals', function () {
    $criteria = [crit(1, 'must_have', ['php'])];
    for ($i = 0; $i < 8; $i++) {
        // 8 nice-to-have criteria; CV matches exactly one (docker).
        $criteria[] = crit(10 + $i, 'nice_to_have', [$i === 0 ? 'docker' : "zz{$i}"]);
    }
    // must_have 60; nice 25*1/8 = 3.125; fit empty 15; total 78.125 -> 78.13 (half-up; half-even would be 78.12).
    $result = matcher()->match('PHP Docker', $criteria);

    expect(number_format($result->score, 2))->toBe('78.13');
});
