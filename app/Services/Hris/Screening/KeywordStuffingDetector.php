<?php

namespace App\Services\Hris\Screening;

use App\Services\Hris\Screening\Dto\StuffingOutcomeDto;

class KeywordStuffingDetector
{
    public function __construct(private array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('screening.keyword_stuffing', []));
    }

    /**
     * @param  string[]  $tokens  every token in the CV text
     * @param  array<string, int>  $matchedCounts  matched keyword => occurrence count
     */
    public function analyze(array $tokens, array $matchedCounts): StuffingOutcomeDto
    {
        $total = max(count($tokens), 1);
        $density = array_sum($matchedCounts) / $total * 100;
        $maxRepeat = $matchedCounts === [] ? 0 : max($matchedCounts);

        $densityWarning = (float) ($this->config['density_warning'] ?? 8.0);
        $densityCritical = (float) ($this->config['density_critical'] ?? 15.0);
        $repeatWarning = (int) ($this->config['single_repeat_warning'] ?? 8);
        $repeatCritical = (int) ($this->config['single_repeat_critical'] ?? 15);
        $densityApplies = count($tokens) >= (int) ($this->config['min_tokens_for_density'] ?? 0);

        if (($densityApplies && $density >= $densityCritical) || $maxRepeat >= $repeatCritical) {
            $severity = StuffingOutcomeDto::SEVERITY_CRITICAL;
        } elseif (($densityApplies && $density >= $densityWarning) || $maxRepeat >= $repeatWarning) {
            $severity = StuffingOutcomeDto::SEVERITY_WARNING;
        } else {
            $severity = StuffingOutcomeDto::SEVERITY_NONE;
        }

        return new StuffingOutcomeDto($severity, round($density, 2), $maxRepeat);
    }
}
