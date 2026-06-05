<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Carries a machine-readable `reason` that maps directly to the result's
 * parser_failure_reason: pdf_text_binary_missing | pdf_text_empty | pdf_text_extraction_failed.
 */
class ResumeParseException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
