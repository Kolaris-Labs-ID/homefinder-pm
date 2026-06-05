<?php

use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Services\Hris\Screening\CriteriaSnapshotService;

function snapshotJob(): JobPosting
{
    return JobPosting::factory()->create([
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
}

it('snapshots only active criteria with the synonym dictionary version', function () {
    $job = snapshotJob();
    ApplicantScreeningCriteria::create(['job_posting_id' => $job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);
    ApplicantScreeningCriteria::create(['job_posting_id' => $job->id, 'group' => 'nice_to_have', 'label' => 'Docker', 'keywords' => ['docker'], 'is_active' => false]);

    $snapshot = app(CriteriaSnapshotService::class)->snapshot($job);

    expect($snapshot->criteria)->toHaveCount(1)
        ->and($snapshot->criteria[0]['label'])->toBe('PHP')
        ->and($snapshot->synonymDictionaryVersion)->toBe('1');
});

it('freezes the captured snapshot so later criteria edits do not change its hash', function () {
    $job = snapshotJob();
    $criterion = ApplicantScreeningCriteria::create(['job_posting_id' => $job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);

    $service = app(CriteriaSnapshotService::class);
    $snapshot = $service->snapshot($job);
    $hash = $service->hash($snapshot);

    $criterion->update(['weight' => 5]);

    // The captured snapshot is unchanged; a fresh snapshot reflects the edit.
    expect($service->hash($snapshot))->toBe($hash)
        ->and($service->hash($service->snapshot($job->fresh())))->not->toBe($hash);
});

it('produces a deterministic hash for an identical snapshot', function () {
    $job = snapshotJob();
    ApplicantScreeningCriteria::create(['job_posting_id' => $job->id, 'group' => 'must_have', 'label' => 'PHP', 'keywords' => ['php']]);

    $service = app(CriteriaSnapshotService::class);

    expect($service->hash($service->snapshot($job)))->toBe($service->hash($service->snapshot($job)));
});
