<?php

namespace App\Services\Hris\Screening;

/**
 * Directional synonym resolution. Given a criterion term, returns the set of
 * CV-side terms that would satisfy it.
 *
 *   source_implies_target: a source term satisfies a criterion targeting the
 *   target term, but not the reverse (react -> frontend; frontend !-> react).
 *   bidirectional: either side satisfies the other (akuntansi <-> accounting).
 *
 * Terms absent from the dictionary resolve to themselves only (java, javascript).
 */
class SynonymResolver
{
    public function __construct(private array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('screening.synonyms', ['version' => '0', 'entries' => []]));
    }

    public function version(): string
    {
        return (string) ($this->config['version'] ?? '0');
    }

    /**
     * CV-side terms that satisfy the given criterion term (includes the term itself).
     *
     * @return string[]
     */
    public function termsSatisfying(string $criterionTerm): array
    {
        $term = $this->normalize($criterionTerm);
        $satisfying = [$term];

        foreach ($this->entries() as $entry) {
            $target = $this->normalize($entry['target_term'] ?? '');
            $sources = array_map(fn ($s) => $this->normalize($s), $entry['source_terms'] ?? []);
            $direction = $entry['direction'] ?? 'source_implies_target';

            // A source term always satisfies the target (source -> target).
            if ($term === $target) {
                $satisfying = array_merge($satisfying, $sources);
            }

            // Only bidirectional entries let the target satisfy a source.
            if ($direction === 'bidirectional' && in_array($term, $sources, true)) {
                $satisfying[] = $target;
                $satisfying = array_merge($satisfying, array_diff($sources, [$term]));
            }
        }

        return array_values(array_unique($satisfying));
    }

    /**
     * Synonym alternates only (excludes the criterion term itself).
     *
     * @return string[]
     */
    public function synonymsFor(string $criterionTerm): array
    {
        return array_values(array_diff(
            $this->termsSatisfying($criterionTerm),
            [$this->normalize($criterionTerm)],
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function entries(): array
    {
        return (array) ($this->config['entries'] ?? []);
    }

    private function normalize(string $term): string
    {
        return trim(mb_strtolower($term));
    }
}
