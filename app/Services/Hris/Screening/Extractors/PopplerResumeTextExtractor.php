<?php

namespace App\Services\Hris\Screening\Extractors;

use App\Exceptions\ResumeParseException;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use Spatie\PdfToText\Pdf;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Wraps the poppler `pdftotext` binary (spatie/pdf-to-text). A missing binary or any
 * extraction failure degrades to a ResumeParseException carrying a reason — the worker
 * records `parser_status=failed` / `needs_manual_review` rather than crashing.
 *
 * `rawExtractor` is injectable so tests stay hermetic (no system binary required).
 */
class PopplerResumeTextExtractor implements ResumeTextExtractor
{
    /** @var (callable(string):string)|null */
    private $rawExtractor;

    private int $maxChars;

    public function __construct(?callable $rawExtractor = null, ?int $maxChars = null, private string $binary = 'pdftotext')
    {
        $this->rawExtractor = $rawExtractor;
        $this->maxChars = $maxChars ?? (int) config('screening.max_text_chars', 100000);
    }

    public function extract(string $absolutePath): string
    {
        try {
            $text = $this->rawExtractor !== null
                ? ($this->rawExtractor)($absolutePath)
                : $this->runPoppler($absolutePath);
        } catch (ResumeParseException $e) {
            throw $e; // preserve a specific reason (e.g. pdf_text_binary_missing)
        } catch (\Throwable $e) {
            throw new ResumeParseException('pdf_text_extraction_failed', $e->getMessage(), $e);
        }

        $text = trim((string) $text);

        if ($text === '') {
            throw new ResumeParseException('pdf_text_empty', 'No extractable text (image-only or empty PDF).');
        }

        if (mb_strlen($text) > $this->maxChars) {
            $text = mb_substr($text, 0, $this->maxChars);
        }

        return $text;
    }

    private function runPoppler(string $absolutePath): string
    {
        if (! self::binaryAvailable($this->binary)) {
            throw new ResumeParseException('pdf_text_binary_missing', "pdftotext binary '{$this->binary}' not found on PATH.");
        }

        return (string) Pdf::getText($absolutePath);
    }

    public static function binaryAvailable(string $binary = 'pdftotext'): bool
    {
        return (new ExecutableFinder)->find($binary) !== null;
    }
}
