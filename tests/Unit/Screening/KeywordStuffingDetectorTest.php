<?php

use App\Services\Hris\Screening\KeywordStuffingDetector;

function stuffing(): KeywordStuffingDetector
{
    return KeywordStuffingDetector::fromConfig();
}

it('reports none for natural text', function () {
    $tokens = array_merge(array_fill(0, 98, 'word'), ['php', 'laravel']);
    expect(stuffing()->analyze($tokens, ['php' => 1, 'laravel' => 1])->severity)->toBe('none');
});

it('flags a warning at or above 8% matched-keyword density', function () {
    $tokens = array_merge(array_fill(0, 92, 'word'), array_fill(0, 8, 'php'));
    expect(stuffing()->analyze($tokens, ['php' => 8])->severity)->toBe('warning');
});

it('flags critical at or above 15 single-keyword repeats even when density is low', function () {
    $tokens = array_merge(array_fill(0, 200, 'word'), array_fill(0, 15, 'php'));
    $outcome = stuffing()->analyze($tokens, ['php' => 15]);

    expect($outcome->severity)->toBe('critical')
        ->and($outcome->maxRepeat)->toBe(15);
});
