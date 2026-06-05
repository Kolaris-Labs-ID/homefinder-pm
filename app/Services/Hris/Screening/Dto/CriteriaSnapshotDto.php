<?php

namespace App\Services\Hris\Screening\Dto;

/**
 * Frozen copy of the active criteria a result was scored against. Matching uses the
 * snapshot, never live criteria, so later edits/deletes never change a result's meaning.
 */
final class CriteriaSnapshotDto
{
    public const SCHEMA_VERSION = '1';

    /**
     * @param  array<int, array<string, mixed>>  $criteria  each: id, group, label,
     *                                                      keywords, synonyms, weight,
     *                                                      missing_means_failed, is_active, sort_order
     */
    public function __construct(
        public readonly array $criteria,
        public readonly string $synonymDictionaryVersion,
    ) {}

    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'criteria' => $this->criteria,
            'synonym_dictionary_version' => $this->synonymDictionaryVersion,
        ];
    }
}
