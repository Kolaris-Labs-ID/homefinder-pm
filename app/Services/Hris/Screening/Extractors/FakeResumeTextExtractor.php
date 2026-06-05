<?php

namespace App\Services\Hris\Screening\Extractors;

use App\Exceptions\ResumeParseException;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;

/**
 * Test double: returns canned text or simulates a parse failure, so orchestration and
 * queue tests never shell out to pdftotext.
 */
class FakeResumeTextExtractor implements ResumeTextExtractor
{
    public function __construct(private string $text = '', private ?ResumeParseException $failure = null) {}

    public static function returning(string $text): self
    {
        return new self($text);
    }

    public static function failing(string $reason): self
    {
        return new self('', new ResumeParseException($reason));
    }

    public function extract(string $absolutePath): string
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->text;
    }
}
