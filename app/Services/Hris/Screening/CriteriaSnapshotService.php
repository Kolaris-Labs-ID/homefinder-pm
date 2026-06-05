<?php

namespace App\Services\Hris\Screening;

use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\JobPosting;
use App\Services\Hris\Screening\Dto\CriteriaSnapshotDto;

/**
 * Freezes the active criteria a result is scored against. Matching uses this snapshot,
 * never live criteria, so later edits/deletes never change a finalized result's meaning.
 * The hash is canonical (deep key-sort) so it depends only on content, not key order.
 */
class CriteriaSnapshotService
{
    private SynonymResolver $synonyms;

    public function __construct(?SynonymResolver $synonyms = null)
    {
        $this->synonyms = $synonyms ?? SynonymResolver::fromConfig();
    }

    public function snapshot(JobPosting $job): CriteriaSnapshotDto
    {
        $criteria = ApplicantScreeningCriteria::query()
            ->where('job_posting_id', $job->id)
            ->where('is_active', true)
            ->orderBy('id') // deterministic order, independent of sort_order edits
            ->get()
            ->map(fn (ApplicantScreeningCriteria $c) => [
                'id' => (int) $c->id,
                'group' => $c->group,
                'label' => $c->label,
                'keywords' => $c->keywords ?? [],
                'synonyms' => $c->synonyms ?? [],
                'weight' => (int) $c->weight,
                'missing_means_failed' => (bool) $c->missing_means_failed,
                'is_active' => (bool) $c->is_active,
                'sort_order' => (int) $c->sort_order,
            ])
            ->all();

        return new CriteriaSnapshotDto($criteria, $this->synonyms->version());
    }

    public function hash(CriteriaSnapshotDto $snapshot): string
    {
        return hash('sha256', (string) json_encode($this->sortDeep($snapshot->toArray())));
    }

    private function sortDeep(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $value = array_map(fn ($v) => $this->sortDeep($v), $value);
        if (! $isList) {
            ksort($value);
        }

        return $value;
    }
}
