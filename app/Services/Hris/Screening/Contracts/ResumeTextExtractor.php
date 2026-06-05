<?php

namespace App\Services\Hris\Screening\Contracts;

use App\Exceptions\ResumeParseException;

interface ResumeTextExtractor
{
    /**
     * Extract plain text from a PDF at an absolute path.
     *
     * @throws ResumeParseException on any failure (binary missing, empty/image-only,
     *                              corrupt) — never a raw 500.
     */
    public function extract(string $absolutePath): string;
}
