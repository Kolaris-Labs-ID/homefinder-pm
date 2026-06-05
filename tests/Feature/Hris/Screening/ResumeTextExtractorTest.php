<?php

use App\Exceptions\ResumeParseException;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\PopplerResumeTextExtractor;

it('binds the poppler extractor as the default ResumeTextExtractor', function () {
    expect(app(ResumeTextExtractor::class))->toBeInstanceOf(PopplerResumeTextExtractor::class);
});

it('fake extractor returns canned text', function () {
    expect(FakeResumeTextExtractor::returning('hello cv')->extract('/x.pdf'))->toBe('hello cv');
});

it('fake extractor can simulate a parse failure', function () {
    expect(fn () => FakeResumeTextExtractor::failing('pdf_text_empty')->extract('/x.pdf'))
        ->toThrow(ResumeParseException::class);
});

it('caps extracted text to the configured maximum', function () {
    $extractor = new PopplerResumeTextExtractor(rawExtractor: fn () => str_repeat('a', 250000), maxChars: 100);

    expect(mb_strlen($extractor->extract('/x.pdf')))->toBe(100);
});

it('treats empty extraction as a parse failure (image-only / empty pdf)', function () {
    $extractor = new PopplerResumeTextExtractor(rawExtractor: fn () => "   \n  ");

    try {
        $extractor->extract('/x.pdf');
        $this->fail('expected ResumeParseException');
    } catch (ResumeParseException $e) {
        expect($e->reason)->toBe('pdf_text_empty');
    }
});

it('reports a missing pdftotext binary as pdf_text_binary_missing, not a 500', function () {
    $extractor = new PopplerResumeTextExtractor(binary: 'definitely-not-a-real-binary-xyz');

    try {
        $extractor->extract('/x.pdf');
        $this->fail('expected ResumeParseException');
    } catch (ResumeParseException $e) {
        expect($e->reason)->toBe('pdf_text_binary_missing');
    }
});
