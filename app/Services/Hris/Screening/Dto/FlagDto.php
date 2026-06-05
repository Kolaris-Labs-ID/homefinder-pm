<?php

namespace App\Services\Hris\Screening\Dto;

final class FlagDto
{
    public const SCHEMA_VERSION = '1';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public readonly string $code,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $context = [],
    ) {}

    public function toArray(): array
    {
        $out = [
            'schema_version' => self::SCHEMA_VERSION,
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
        ];

        if ($this->context !== []) {
            $out['context'] = $this->context;
        }

        return $out;
    }
}
