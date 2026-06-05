<?php

namespace App\Services\Hris\Screening\Dto;

/**
 * Outcome of matching a CV against a criteria snapshot. The matcher always assumes
 * parsed text, so `score` is always numeric here; the orchestrator substitutes null
 * for parser failures / not-yet-screened states.
 */
final class MatchResultDto
{
    /**
     * @param  FlagDto[]  $flags
     * @param  MatchedCriterionDto[]  $matched
     * @param  MissingCriterionDto[]  $missing
     * @param  FitOutcomeDto[]  $fitOutcomes
     */
    public function __construct(
        public readonly ?float $score,
        public readonly string $recommendation,
        public readonly array $flags,
        public readonly array $matched,
        public readonly array $missing,
        public readonly array $fitOutcomes,
        public readonly ScoringBreakdownDto $breakdown,
    ) {}
}
