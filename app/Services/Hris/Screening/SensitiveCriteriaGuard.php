<?php

namespace App\Services\Hris\Screening;

use App\Exceptions\SensitiveScreeningCriteriaException;

/**
 * Fairness guard: screening criteria may never key off protected attributes
 * (religion, gender, age, NIK, DOB, marital status, photo, …). Enforced here at
 * the service level and called from the model `saving` hook, so the block holds
 * regardless of entry path — not only the Filament form.
 *
 * Matching is word-aware (custom Unicode boundaries) so "manager"/"storage" (age)
 * and "teknik" (nik) are NOT false positives.
 */
class SensitiveCriteriaGuard
{
    /** @return string[] */
    public static function terms(): array
    {
        return array_filter(array_map(
            fn ($t) => mb_strtolower(trim((string) $t)),
            (array) config('screening.sensitive_terms', []),
        ));
    }

    public function assertClean(?string $label, ?array $keywords = [], ?array $synonyms = []): void
    {
        $haystacks = array_merge(
            [(string) $label],
            array_map('strval', $keywords ?? []),
            array_map('strval', $synonyms ?? []),
        );

        foreach ($haystacks as $value) {
            if (($hit = $this->firstSensitiveHit($value)) !== null) {
                throw new SensitiveScreeningCriteriaException(
                    "Screening criteria may not reference the protected attribute \"{$hit}\".",
                );
            }
        }
    }

    /** Offending sensitive term in a single value, or null. */
    public function firstHit(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $this->firstSensitiveHit($value);
    }

    /** Offending sensitive term across a list of values, or null. */
    public function firstHitInList(?array $values): ?string
    {
        foreach ($values ?? [] as $value) {
            if (($hit = $this->firstHit((string) $value)) !== null) {
                return $hit;
            }
        }

        return null;
    }

    private function firstSensitiveHit(string $value): ?string
    {
        $normalized = mb_strtolower($value);

        foreach (self::terms() as $term) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $normalized) === 1) {
                return $term;
            }
        }

        return null;
    }
}
