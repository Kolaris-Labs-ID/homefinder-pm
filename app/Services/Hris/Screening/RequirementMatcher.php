<?php

namespace App\Services\Hris\Screening;

use App\Services\Hris\Screening\Dto\FitOutcomeDto;
use App\Services\Hris\Screening\Dto\FlagDto;
use App\Services\Hris\Screening\Dto\MatchedCriterionDto;
use App\Services\Hris\Screening\Dto\MatchResultDto;
use App\Services\Hris\Screening\Dto\MissingCriterionDto;

/**
 * Deterministic, rule-based matcher. Order/whitespace/keyword-order never change the
 * score. No AI. Must-have/nice-to-have/fit are weighted-scored from the criteria
 * snapshot (§5.4); knockout criteria gate the recommendation; the four built-in fit
 * checks are advisory context (fit_outcomes + flags), not part of the numeric score.
 */
class RequirementMatcher
{
    private const NEGATIONS = ['tidak', 'no', 'without', 'bukan', 'tanpa', 'non'];

    private SynonymResolver $synonyms;

    private ScreeningScorer $scorer;

    private KeywordStuffingDetector $stuffing;

    public function __construct(?SynonymResolver $synonyms = null, ?ScreeningScorer $scorer = null, ?KeywordStuffingDetector $stuffing = null)
    {
        $this->synonyms = $synonyms ?? SynonymResolver::fromConfig();
        $this->scorer = $scorer ?? new ScreeningScorer;
        $this->stuffing = $stuffing ?? KeywordStuffingDetector::fromConfig();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria  criteria snapshot
     * @param  array<string, mixed>  $fitContext  job/applicant data for fit checks
     */
    public function match(string $cvText, array $criteria, ?\DateTimeInterface $referenceDate = null, array $fitContext = []): MatchResultDto
    {
        $text = $this->normalizeText($cvText);
        $tokens = $this->tokenize($text);

        $groups = [
            'must_have' => ['matched' => 0, 'total' => 0, 'active' => false],
            'nice_to_have' => ['matched' => 0, 'total' => 0, 'active' => false],
            'fit' => ['matched' => 0, 'total' => 0, 'active' => false],
        ];
        $matched = [];
        $missing = [];
        $flags = [];
        $matchedKeywordCounts = [];
        $knockoutFailed = false;
        $mustHaveActive = 0;
        $mustHaveMatched = 0;

        foreach ($criteria as $criterion) {
            if (! ($criterion['is_active'] ?? true)) {
                continue;
            }

            $group = (string) ($criterion['group'] ?? '');
            $weight = max(1, (int) ($criterion['weight'] ?? 1));
            [$isMatch, $terms, $via] = $this->matchCriterion($text, $criterion);

            if ($group === 'knockout') {
                if (! $isMatch) {
                    if ($criterion['missing_means_failed'] ?? false) {
                        $knockoutFailed = true;
                        $flags[] = new FlagDto('knockout_failed', FlagDto::SEVERITY_CRITICAL, 'Knockout requirement not met: '.$criterion['label']);
                    } else {
                        $flags[] = new FlagDto('knockout_unknown', FlagDto::SEVERITY_WARNING, 'Knockout requirement could not be confirmed: '.$criterion['label']);
                    }
                }

                continue; // knockout is gating, never scored
            }

            if (! isset($groups[$group])) {
                continue; // ignore unknown groups
            }

            $groups[$group]['active'] = true;
            $groups[$group]['total'] += $weight;
            if ($group === 'must_have') {
                $mustHaveActive++;
            }

            if ($isMatch) {
                $groups[$group]['matched'] += $weight;
                if ($group === 'must_have') {
                    $mustHaveMatched++;
                }
                $matched[] = new MatchedCriterionDto((int) $criterion['id'], $group, (string) $criterion['label'], $weight, $terms, $via);
                foreach ($terms as $term) {
                    $matchedKeywordCounts[$term] = ($matchedKeywordCounts[$term] ?? 0) + $this->countOccurrences($text, $term);
                }
            } else {
                $missing[] = new MissingCriterionDto((int) $criterion['id'], $group, (string) $criterion['label'], $weight);
            }
        }

        foreach (['must_have', 'nice_to_have', 'fit'] as $group) {
            if (! $groups[$group]['active']) {
                $flags[] = new FlagDto('criteria_group_empty:'.$group, FlagDto::SEVERITY_INFO, "No active {$group} criteria; full group points granted.");
                if ($group === 'must_have') {
                    $flags[] = new FlagDto('criteria_missing', FlagDto::SEVERITY_WARNING, 'No active must-have criteria configured for this job.');
                }
            }
        }

        $fitOutcomes = $this->fitChecks($fitContext);
        foreach ($fitOutcomes as $outcome) {
            if ($outcome->outcome === FitOutcomeDto::OUTCOME_FAILED) {
                $flags[] = new FlagDto('fit_'.$outcome->kind.'_failed', FlagDto::SEVERITY_WARNING, 'Fit check failed: '.$outcome->kind.($outcome->detail ? ' ('.$outcome->detail.')' : ''));
            }
        }

        $stuffingOutcome = $this->stuffing->analyze($tokens, $matchedKeywordCounts);
        if ($stuffingOutcome->severity !== 'none') {
            $flags[] = new FlagDto(
                'abnormal_keyword_density',
                $stuffingOutcome->severity === 'critical' ? FlagDto::SEVERITY_CRITICAL : FlagDto::SEVERITY_WARNING,
                'Abnormal keyword density: '.$stuffingOutcome->density.'%.',
            );
        }

        $allMustHavesMatched = $mustHaveActive === 0 || $mustHaveMatched === $mustHaveActive;
        $scored = $this->scorer->score($groups, $allMustHavesMatched, $knockoutFailed, $stuffingOutcome->severity);
        $flags = array_merge($flags, $scored['capFlags']);

        return new MatchResultDto(
            score: $scored['breakdown']->total,
            recommendation: $scored['recommendation'],
            flags: $flags,
            matched: $matched,
            missing: $missing,
            fitOutcomes: $fitOutcomes,
            breakdown: $scored['breakdown'],
        );
    }

    /**
     * @return array{0: bool, 1: string[], 2: string} [matched, matchedTerms, via]
     */
    private function matchCriterion(string $text, array $criterion): array
    {
        $keywords = array_filter(array_map(fn ($k) => $this->normalizeTerm($k), $criterion['keywords'] ?? []));

        $hits = [];
        foreach ($keywords as $keyword) {
            if ($this->present($text, $keyword)) {
                $hits[] = $keyword;
            }
        }
        if ($hits !== []) {
            return [true, array_values(array_unique($hits)), MatchedCriterionDto::VIA_KEYWORD];
        }

        $synonymTerms = array_map(fn ($s) => $this->normalizeTerm($s), $criterion['synonyms'] ?? []);
        foreach ($keywords as $keyword) {
            $synonymTerms = array_merge($synonymTerms, $this->synonyms->synonymsFor($keyword));
        }
        $synonymTerms = array_filter(array_unique($synonymTerms));

        $synonymHits = [];
        foreach ($synonymTerms as $term) {
            if ($this->present($text, $term)) {
                $synonymHits[] = $term;
            }
        }
        if ($synonymHits !== []) {
            return [true, array_values(array_unique($synonymHits)), MatchedCriterionDto::VIA_SYNONYM];
        }

        return [false, [], MatchedCriterionDto::VIA_KEYWORD];
    }

    private function present(string $text, string $term): bool
    {
        if ($term === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u';
        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE) < 1) {
            return false;
        }

        foreach ($matches[0] as [$found, $offset]) {
            if (! $this->isNegated($text, $offset)) {
                return true;
            }
        }

        return false;
    }

    private function isNegated(string $text, int $offset): bool
    {
        $window = substr($text, max(0, $offset - 20), min(20, $offset));
        $words = preg_split('/\s+/', trim($window)) ?: [];
        foreach (array_slice($words, -2) as $word) {
            if (in_array($word, self::NEGATIONS, true)) {
                return true;
            }
        }

        return false;
    }

    private function countOccurrences(string $text, string $term): int
    {
        return preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $text);
    }

    /**
     * Advisory fit checks. Only the salary rule is fully pinned (Q5); the rest emit an
     * outcome only when there is enough context, and never crash on missing data.
     *
     * @return FitOutcomeDto[]
     */
    private function fitChecks(array $ctx): array
    {
        $outcomes = [];

        // Salary (Q5): expected > visible max => failed; hidden/absent => unknown.
        if (array_key_exists('expected_salary', $ctx) || array_key_exists('salary_max', $ctx)) {
            $hidden = (bool) ($ctx['salary_hidden'] ?? false);
            $max = $ctx['salary_max'] ?? null;
            $expected = $ctx['expected_salary'] ?? null;

            if ($hidden || $max === null || $expected === null) {
                $outcomes[] = new FitOutcomeDto(FitOutcomeDto::KIND_SALARY, FitOutcomeDto::OUTCOME_UNKNOWN);
            } elseif ((float) $expected > (float) $max) {
                $outcomes[] = new FitOutcomeDto(FitOutcomeDto::KIND_SALARY, FitOutcomeDto::OUTCOME_FAILED, 'expected '.$expected.' > max '.$max);
            } else {
                $outcomes[] = new FitOutcomeDto(FitOutcomeDto::KIND_SALARY, FitOutcomeDto::OUTCOME_MATCHED);
            }
        }

        return $outcomes;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    private function normalizeTerm(string $term): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($term)));
    }

    /** @return string[] */
    private function tokenize(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
