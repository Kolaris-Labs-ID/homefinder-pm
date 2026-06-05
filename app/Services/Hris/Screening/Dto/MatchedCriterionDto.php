<?php

namespace App\Services\Hris\Screening\Dto;

final class MatchedCriterionDto
{
    public const SCHEMA_VERSION = '1';

    public const VIA_KEYWORD = 'keyword';

    public const VIA_SYNONYM = 'synonym';

    public function __construct(
        public readonly int $criterionId,
        public readonly string $group,
        public readonly string $label,
        public readonly int $weight,
        public readonly array $matchedTerms,
        public readonly string $via,
    ) {}

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'criterion_id' => $this->criterionId,
            'group' => $this->group,
            'label' => $this->label,
            'weight' => $this->weight,
            'matched_terms' => array_values($this->matchedTerms),
            'via' => $this->via,
        ];
    }
}
