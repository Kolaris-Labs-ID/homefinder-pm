<?php

it('exposes a version for every screening component', function () {
    expect(config('screening.versions.parser'))->toBe('1')
        ->and(config('screening.versions.matcher'))->toBe('1')
        ->and(config('screening.versions.screening'))->toBe('1')
        ->and(config('screening.versions.synonym'))->toBe('1');
});

it('caps extracted text and persisted summary length', function () {
    expect(config('screening.max_text_chars'))->toBe(100000)
        ->and(config('screening.summary_max_chars'))->toBe(2000);
});

it('blocks Indonesian and English sensitive terms', function () {
    $terms = array_map('strtolower', config('screening.sensitive_terms'));

    foreach ([
        'agama', 'religion', 'jenis kelamin', 'gender', 'umur', 'usia', 'age',
        'tanggal lahir', 'date of birth', 'nik', 'foto', 'photo',
        'status menikah', 'marital status',
    ] as $needle) {
        expect($terms)->toContain($needle);
    }
});

it('defines a versioned, directional synonym dictionary', function () {
    expect(config('screening.synonyms.version'))->toBe('1');

    $entries = collect(config('screening.synonyms.entries'));

    $react = $entries->first(fn ($e) => in_array('react', $e['source_terms'], true) && $e['target_term'] === 'frontend');
    expect($react)->not->toBeNull()
        ->and($react['direction'])->toBe('source_implies_target');

    $accounting = $entries->first(fn ($e) => $e['target_term'] === 'accounting' || in_array('accounting', $e['source_terms'], true));
    expect($accounting)->not->toBeNull()
        ->and($accounting['direction'])->toBe('bidirectional');
});

it('pins keyword stuffing thresholds', function () {
    expect(config('screening.keyword_stuffing.density_warning'))->toBe(8.0)
        ->and(config('screening.keyword_stuffing.density_critical'))->toBe(15.0)
        ->and(config('screening.keyword_stuffing.single_repeat_warning'))->toBe(8)
        ->and(config('screening.keyword_stuffing.single_repeat_critical'))->toBe(15);
});
