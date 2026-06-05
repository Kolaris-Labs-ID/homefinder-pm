<?php

namespace App\Services\Hris\Screening;

/**
 * Produces a redacted, length-capped summary for persistence. The full parsed CV text
 * is NEVER stored — only this minimized summary. Lines naming protected attributes are
 * dropped; NIK / dates / contact details are masked in place.
 */
class ResumeRedactor
{
    private const LABEL_RULES = [
        'agama' => '/\b(agama|religion)\b/i',
        'gender' => '/\b(jenis kelamin|gender)\b/i',
        'marital' => '/\b(status menikah|status pernikahan|marital status)\b/i',
        'pob' => '/\b(tempat lahir|tempat tanggal lahir|place of birth)\b/i',
        'dob' => '/\b(tanggal lahir|date of birth)\b/i',
        'full_address' => '/\b(alamat|address)\b/i',
        'photo' => '/\b(foto|photo)\b/i',
    ];

    private const PATTERN_RULES = [
        'nik' => '/\b\d{16}\b/',
        'dob' => '/\b\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}\b/',
        'contact' => '/[\w.+\-]+@[\w\-]+\.[\w.\-]+|\b(?:\+?62|0)[\d\- ]{8,}\d\b/',
    ];

    private int $maxChars;

    public function __construct(?int $maxChars = null)
    {
        $this->maxChars = $maxChars ?? (int) config('screening.summary_max_chars', 2000);
    }

    /**
     * @return array{summary: string, redacted: string[]}
     */
    public function redact(string $text): array
    {
        $redacted = [];

        // Drop whole lines that name a protected attribute.
        $kept = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $drop = false;
            foreach (self::LABEL_RULES as $code => $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $redacted[$code] = true;
                    $drop = true;
                }
            }
            if (! $drop) {
                $kept[] = $line;
            }
        }
        $out = implode("\n", $kept);

        // Mask sensitive patterns in place.
        foreach (self::PATTERN_RULES as $code => $pattern) {
            $out = preg_replace_callback($pattern, function () use (&$redacted, $code) {
                $redacted[$code] = true;

                return '[REDACTED]';
            }, $out);
        }

        $summary = trim((string) preg_replace('/\n{2,}/', "\n", $out));
        if (mb_strlen($summary) > $this->maxChars) {
            $summary = mb_substr($summary, 0, $this->maxChars);
        }

        return ['summary' => $summary, 'redacted' => array_keys($redacted)];
    }
}
