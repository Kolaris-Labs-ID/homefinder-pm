<?php

use App\Facades\Features;
use App\Features\Feature;
use App\Models\AppFeature;
use App\Models\AppFeatureChange;

it('uses the flag default until the company has chosen', function () {
    config(['features.preset' => null]);

    expect(Features::isInitialized(Feature::Recruitment))->toBeFalse()
        ->and(Features::active(Feature::Recruitment))->toBe(Feature::Recruitment->default());
});

it('uses the deployment preset for flags not stored yet', function () {
    config(['features.preset' => 'kgtb']);

    expect(Features::active(Feature::Recruitment))->toBeFalse()
        ->and(Features::active(Feature::GoogleLogin))->toBeFalse()
        ->and(Features::isInitialized(Feature::Recruitment))->toBeFalse();
});

it('lets the stored choice win over the preset', function () {
    Features::initialize([Feature::Recruitment->value => true], 'test');

    config(['features.preset' => 'kgtb']);

    expect(Features::active(Feature::Recruitment))->toBeTrue();
});

it('stores only flags that have no value yet', function () {
    $first = Features::initialize([Feature::Recruitment->value => false], 'preset:kgtb');
    $second = Features::initialize([
        Feature::Recruitment->value => true,
        Feature::GoogleLogin->value => false,
    ], 'preset:homefinder');

    expect($first)->toBe([Feature::Recruitment])
        ->and($second)->toBe([Feature::GoogleLogin])
        ->and(Features::active(Feature::Recruitment))->toBeFalse()
        ->and(AppFeature::where('key', Feature::Recruitment->value)->value('source'))->toBe('preset:kgtb');
});

it('does not overwrite a value another process stored first', function () {
    // Simulates a second setup racing this one: the row appears after this
    // request already read the table.
    expect(Features::isInitialized(Feature::GoogleLogin))->toBeFalse();

    AppFeature::create([
        'key' => Feature::GoogleLogin->value,
        'value' => false,
        'source' => 'user:1',
        'initialized_at' => now(),
    ]);

    $written = Features::initialize([Feature::GoogleLogin->value => true], 'cli');

    expect($written)->toBe([])
        ->and(Features::active(Feature::GoogleLogin))->toBeFalse();
});

it('sees a value stored by another process on the next request', function () {
    config(['features.preset' => null]);
    expect(Features::active(Feature::Recruitment))->toBeTrue();

    AppFeature::create([
        'key' => Feature::Recruitment->value,
        'value' => false,
        'source' => 'user:1',
        'initialized_at' => now(),
    ]);

    // What Octane does between requests (and the queue worker between jobs).
    app()->forgetScopedInstances();

    expect(Features::active(Feature::Recruitment))->toBeFalse();
});

it('rejects an unknown flag key', function () {
    Features::initialize(['module.unknown' => true], 'cli');
})->throws(InvalidArgumentException::class);

it('changes a locked flag only with a reason, and audits it', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => false], 'preset:kgtb');

    Features::change(Feature::OvertimeStatutoryCaps, true, 'kgtb adopted the statutory caps from November', 'cli');

    expect(Features::active(Feature::OvertimeStatutoryCaps))->toBeTrue();

    $change = AppFeatureChange::sole();
    expect($change->key)->toBe(Feature::OvertimeStatutoryCaps->value)
        ->and($change->old_value)->toBeFalse()
        ->and($change->new_value)->toBeTrue()
        ->and($change->actor)->toBe('cli');
});

it('refuses a change without a reason', function () {
    Features::initialize([Feature::Recruitment->value => true], 'cli');

    Features::change(Feature::Recruitment, false, '  ', 'cli');
})->throws(InvalidArgumentException::class);

it('refuses to change a flag that was never set', function () {
    Features::change(Feature::Recruitment, false, 'reason', 'cli');
})->throws(LogicException::class);

it('lists every flag with its effective value', function () {
    config(['features.preset' => null]);
    Features::initialize([Feature::GoogleLogin->value => false], 'cli');

    expect(Features::all())->toBe([
        'module.recruitment' => true,
        'auth.google_login' => false,
        'payroll.overtime_statutory_caps' => true,
    ]);
});

it('gives every preset a value for every flag', function () {
    foreach (config('features.presets') as $name => $values) {
        foreach (Feature::cases() as $feature) {
            expect($values)->toHaveKey($feature->value, message: "Preset [{$name}] is missing [{$feature->value}].");
        }

        expect(array_diff(array_keys($values), array_map(fn ($f) => $f->value, Feature::cases())))
            ->toBe([], "Preset [{$name}] has a key that is not a Feature.");
    }
});
