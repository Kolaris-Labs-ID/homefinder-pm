<?php

use App\Facades\Features;
use App\Features\Feature;
use App\Models\AppFeature;
use App\Models\AppFeatureChange;

beforeEach(function () {
    config(['features.preset' => null]);
});

it('stores a preset on first setup', function () {
    $this->artisan('app:init', ['--preset' => 'kgtb', '--no-interaction' => true])
        ->assertSuccessful();

    expect(AppFeature::count())->toBe(count(Feature::cases()))
        ->and(AppFeature::pluck('source')->unique()->all())->toBe(['preset:kgtb']);

    Features::flush();
    expect(Features::active(Feature::Recruitment))->toBeFalse();
});

it('reads the preset from APP_FEATURE_PRESET when no option is given', function () {
    config(['features.preset' => 'homefinder']);

    $this->artisan('app:init', ['--no-interaction' => true])->assertSuccessful();

    expect(AppFeature::pluck('source')->unique()->all())->toBe(['preset:homefinder']);
});

it('never changes flags that are already set when run again', function () {
    $this->artisan('app:init', ['--preset' => 'kgtb', '--no-interaction' => true]);
    $this->artisan('app:init', ['--preset' => 'homefinder', '--no-interaction' => true])
        ->expectsOutputToContain('already set')
        ->assertSuccessful();

    Features::flush();
    expect(Features::active(Feature::Recruitment))->toBeFalse()
        ->and(AppFeature::pluck('source')->unique()->all())->toBe(['preset:kgtb']);
});

it('leaves flags unset and still succeeds when run without a preset or a terminal', function () {
    $this->artisan('app:init', ['--no-interaction' => true])
        ->expectsOutputToContain('Not set yet')
        ->assertSuccessful();

    expect(AppFeature::count())->toBe(0);
});

it('asks for each flag when run in a terminal without a preset', function () {
    $this->artisan('app:init')
        ->expectsConfirmation(Feature::Recruitment->label().': '.Feature::Recruitment->description().' Turn it on?', 'no')
        ->expectsConfirmation(Feature::GoogleLogin->label().': '.Feature::GoogleLogin->description().' Turn it on?', 'yes')
        ->expectsConfirmation(Feature::OvertimeStatutoryCaps->label().': '.Feature::OvertimeStatutoryCaps->description().' Turn it on?', 'yes')
        ->expectsConfirmation('Lock these choices? Changing one later needs features:change and a reason.', 'yes')
        ->assertSuccessful();

    Features::flush();
    expect(Features::active(Feature::Recruitment))->toBeFalse()
        ->and(Features::active(Feature::GoogleLogin))->toBeTrue()
        ->and(AppFeature::pluck('source')->unique()->all())->toBe(['cli']);
});

it('fails clearly when run before the migration', function () {
    \Illuminate\Support\Facades\Schema::drop('app_feature_changes');
    \Illuminate\Support\Facades\Schema::drop('app_features');

    $this->artisan('app:init', ['--preset' => 'kgtb', '--no-interaction' => true])
        ->expectsOutputToContain('Run php artisan migrate first')
        ->assertFailed();
});

it('fails on an unknown preset', function () {
    $this->artisan('app:init', ['--preset' => 'nope', '--no-interaction' => true])
        ->assertFailed();

    expect(AppFeature::count())->toBe(0);
});

it('shows each flag and where its value came from', function () {
    Features::initialize([Feature::Recruitment->value => false], 'preset:kgtb');

    $this->artisan('features:status')
        ->expectsOutputToContain('preset:kgtb')
        ->expectsOutputToContain('not set')
        ->assertSuccessful();
});

it('changes a locked flag with a reason after confirmation', function () {
    Features::initialize([Feature::OvertimeStatutoryCaps->value => false], 'preset:kgtb');

    $this->artisan('features:change', [
        'feature' => 'payroll.overtime_statutory_caps',
        'value' => 'on',
        '--reason' => 'Policy change approved by finance',
    ])
        ->expectsConfirmation('Turn Overtime statutory caps on for this company?', 'yes')
        ->assertSuccessful();

    Features::flush();
    expect(Features::active(Feature::OvertimeStatutoryCaps))->toBeTrue()
        ->and(AppFeatureChange::sole()->reason)->toBe('Policy change approved by finance');
});

it('refuses a change without a reason', function () {
    Features::initialize([Feature::Recruitment->value => true], 'cli');

    $this->artisan('features:change', ['feature' => 'module.recruitment', 'value' => 'off'])
        ->assertFailed();

    expect(AppFeatureChange::count())->toBe(0);
});

it('refuses to change a flag that was never set', function () {
    $this->artisan('features:change', [
        'feature' => 'module.recruitment',
        'value' => 'off',
        '--reason' => 'x',
    ])->assertFailed();
});
