<?php

namespace App\Services\Hris\Screening;

use App\Services\Hris\Screening\Dto\FlagDto;
use App\Services\Hris\Screening\Dto\GroupScoreDto;
use App\Services\Hris\Screening\Dto\ScoringBreakdownDto;

/**
 * Pure scoring (spec §5). Group maxima 60/25/15; empty active group grants its full
 * points as a neutral default; group_score = max * matched_weight / total_weight; the
 * final total is rounded ONCE, half-up, to 2 decimals. Recommendation caps are recorded
 * as flags so a capped result always explains itself (§5.6).
 */
class ScreeningScorer
{
    public const GROUP_MAX = [
        'must_have' => 60,
        'nice_to_have' => 25,
        'fit' => 15,
    ];

    private const LADDER = ['low_match' => 1, 'review' => 2, 'strong_match' => 3];

    /**
     * @param  array<string, array{matched:int, total:int, active:bool}>  $groups
     * @return array{breakdown: ScoringBreakdownDto, recommendation: string, capFlags: FlagDto[]}
     */
    public function score(array $groups, bool $allMustHavesMatched, bool $knockoutFailed, string $stuffingSeverity): array
    {
        $groupDtos = [];
        $totalBeforeRound = 0.0;

        foreach (self::GROUP_MAX as $key => $max) {
            $group = $groups[$key] ?? ['matched' => 0, 'total' => 0, 'active' => false];

            if (! $group['active'] || $group['total'] <= 0) {
                $score = (float) $max; // empty group => full neutral points (does not cap the max)
                $groupDtos[$key] = new GroupScoreDto($max, 0, 0, ! $group['active'], $score);
            } else {
                $score = $max * $group['matched'] / $group['total'];
                $groupDtos[$key] = new GroupScoreDto($max, $group['matched'], $group['total'], false, $score);
            }

            $totalBeforeRound += $score;
        }

        $total = round($totalBeforeRound, 2, PHP_ROUND_HALF_UP);
        $breakdown = new ScoringBreakdownDto($groupDtos, $totalBeforeRound, $total);

        $base = $total >= 80.0 ? 'strong_match' : ($total >= 50.0 ? 'review' : 'low_match');
        $capFlags = [];

        // Hard caps: force needs_manual_review regardless of score.
        if ($knockoutFailed) {
            $capFlags[] = new FlagDto('recommendation_capped_knockout_failed', FlagDto::SEVERITY_WARNING, 'Recommendation held for manual review: a knockout requirement failed.');
        }
        if ($stuffingSeverity === 'critical') {
            $capFlags[] = new FlagDto('recommendation_capped_keyword_stuffing', FlagDto::SEVERITY_WARNING, 'Recommendation held for manual review: critical keyword stuffing detected.');
        }
        if ($knockoutFailed || $stuffingSeverity === 'critical') {
            return ['breakdown' => $breakdown, 'recommendation' => 'needs_manual_review', 'capFlags' => $capFlags];
        }

        // Soft ceiling: cannot exceed `review`.
        $ceiling = 'strong_match';
        if (! $allMustHavesMatched || $stuffingSeverity === 'warning') {
            $ceiling = 'review';
        }

        if (self::LADDER[$base] > self::LADDER[$ceiling]) {
            if (! $allMustHavesMatched) {
                $capFlags[] = new FlagDto('recommendation_capped_missing_must_have', FlagDto::SEVERITY_WARNING, 'Recommendation capped at review: a required (must-have) criterion is missing.');
            }
            if ($stuffingSeverity === 'warning') {
                $capFlags[] = new FlagDto('recommendation_capped_keyword_stuffing', FlagDto::SEVERITY_WARNING, 'Recommendation capped at review: abnormal keyword density.');
            }

            return ['breakdown' => $breakdown, 'recommendation' => $ceiling, 'capFlags' => $capFlags];
        }

        return ['breakdown' => $breakdown, 'recommendation' => $base, 'capFlags' => $capFlags];
    }
}
