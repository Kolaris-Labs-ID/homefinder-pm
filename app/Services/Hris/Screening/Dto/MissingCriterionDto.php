<?php

namespace App\Services\Hris\Screening\Dto;

final class MissingCriterionDto
{
    public const SCHEMA_VERSION = '1';

    public function __construct(
        public readonly int $criterionId,
        public readonly string $group,
        public readonly string $label,
        public readonly int $weight,
    ) {}

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'criterion_id' => $this->criterionId,
            'group' => $this->group,
            'label' => $this->label,
            'weight' => $this->weight,
        ];
    }
}
