<?php

namespace App\Services\Hris\Screening\Dto;

final class FitOutcomeDto
{
    public const SCHEMA_VERSION = '1';

    public const KIND_LOCATION = 'location';

    public const KIND_WORK_ARRANGEMENT = 'work_arrangement';

    public const KIND_SALARY = 'salary';

    public const KIND_AVAILABILITY = 'availability';

    public const OUTCOME_MATCHED = 'matched';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $kind,
        public readonly string $outcome,
        public readonly ?string $detail = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'schema_version' => self::SCHEMA_VERSION,
            'kind' => $this->kind,
            'outcome' => $this->outcome,
        ];

        if ($this->detail !== null) {
            $out['detail'] = $this->detail;
        }

        return $out;
    }
}
