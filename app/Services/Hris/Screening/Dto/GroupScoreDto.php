<?php

namespace App\Services\Hris\Screening\Dto;

/**
 * Per-group scoring detail. Nested inside ScoringBreakdownDto, which carries the
 * shared schema_version — group entries do not repeat it.
 */
final class GroupScoreDto
{
    public function __construct(
        public readonly int $max,
        public readonly int $matchedWeight,
        public readonly int $totalWeight,
        public readonly bool $empty,
        public readonly float $score,
    ) {}

    public function toArray(): array
    {
        return [
            'max' => $this->max,
            'matched_weight' => $this->matchedWeight,
            'total_weight' => $this->totalWeight,
            'empty' => $this->empty,
            'score' => $this->score,
        ];
    }
}
