<?php

namespace App\Services\Hris\Screening\Dto;

final class StuffingOutcomeDto
{
    public const SEVERITY_NONE = 'none';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public readonly string $severity,
        public readonly float $density,
        public readonly int $maxRepeat,
    ) {}
}
