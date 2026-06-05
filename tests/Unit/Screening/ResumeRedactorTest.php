<?php

use App\Services\Hris\Screening\ResumeRedactor;

it('redacts NIK, DOB, address and contact details but keeps professional content', function () {
    $text = implode("\n", [
        'Senior Engineer',
        'NIK: 3201234567890123',
        'Tanggal Lahir: 01/02/1990',
        'Alamat: Jl. Merdeka No. 5, Jakarta',
        'Email: a@b.com',
        'Skills: PHP, Laravel, MySQL',
    ]);

    $result = app(ResumeRedactor::class)->redact($text);

    expect($result['summary'])->not->toContain('3201234567890123')
        ->and($result['summary'])->not->toContain('01/02/1990')
        ->and($result['summary'])->not->toContain('Jl. Merdeka')
        ->and($result['summary'])->not->toContain('a@b.com')
        ->and($result['summary'])->toContain('PHP')
        ->and($result['redacted'])->toContain('nik');
});

it('caps the summary to the configured maximum length', function () {
    $result = app(ResumeRedactor::class)->redact(str_repeat('word ', 3000));

    expect(mb_strlen($result['summary']))->toBeLessThanOrEqual(config('screening.summary_max_chars'));
});
