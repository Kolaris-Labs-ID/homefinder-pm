<?php

use App\Services\Hris\Screening\SynonymResolver;

function resolver(): SynonymResolver
{
    return SynonymResolver::fromConfig();
}

it('treats react as implying frontend (source -> target)', function () {
    expect(resolver()->termsSatisfying('frontend'))->toContain('react');
});

it('does not treat frontend as implying react (no reverse)', function () {
    expect(resolver()->termsSatisfying('react'))->not->toContain('frontend');
});

it('resolves bidirectional accounting <-> akuntansi both ways', function () {
    expect(resolver()->termsSatisfying('accounting'))->toContain('akuntansi')
        ->and(resolver()->termsSatisfying('akuntansi'))->toContain('accounting');
});

it('never links java and javascript', function () {
    expect(resolver()->termsSatisfying('java'))->not->toContain('javascript')
        ->and(resolver()->termsSatisfying('javascript'))->not->toContain('java');
});

it('always includes the criterion term itself', function () {
    expect(resolver()->termsSatisfying('frontend'))->toContain('frontend');
});

it('exposes the synonym dictionary version', function () {
    expect(resolver()->version())->toBe('1');
});

it('synonymsFor excludes the term itself and stays one-directional', function () {
    expect(resolver()->synonymsFor('react'))->toBe([]); // nothing implies react
    expect(resolver()->synonymsFor('frontend'))->toContain('react');
});
