<?php

namespace App\Services\Hris\Screening\Dto;

final class ScoringBreakdownDto
{
    public const SCHEMA_VERSION = '1';

    /**
     * @param  array<string, GroupScoreDto>  $groups  keyed by must_have|nice_to_have|fit
     */
    public function __construct(
        public readonly array $groups,
        public readonly float $totalBeforeRound,
        public readonly float $total,
    ) {}

    public function toArray(): array
    {
        $groups = [];
        foreach ($this->groups as $key => $group) {
            $groups[$key] = $group->toArray();
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'groups' => $groups,
            'total_before_round' => $this->totalBeforeRound,
            'total' => $this->total,
        ];
    }
}
