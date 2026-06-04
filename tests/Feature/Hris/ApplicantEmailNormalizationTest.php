<?php

use App\Models\Hris\Applicant;

it('lowercases and trims the normalized email on save', function () {
    $a = Applicant::factory()->create(['email' => '  John.Doe@Example.COM  ']);

    expect($a->normalized_email)->toBe('john.doe@example.com');
});

it('preserves plus-addressing (does not strip tags)', function () {
    $a = Applicant::factory()->create(['email' => 'jane+jobs@gmail.com']);

    expect($a->normalized_email)->toBe('jane+jobs@gmail.com');
});

it('preserves gmail dots (does not canonicalize)', function () {
    $a = Applicant::factory()->create(['email' => 'John.Doe@gmail.com']);

    expect($a->normalized_email)->toBe('john.doe@gmail.com');
});

it('exposes a reusable normalizeEmail helper', function () {
    expect(Applicant::normalizeEmail('  FOO@Bar.com '))->toBe('foo@bar.com');
});
