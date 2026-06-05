<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\ApplicantScreeningOverride;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;

function screeningJob(): JobPosting
{
    return JobPosting::factory()->create([
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
}

it('rejects a criteria weight below 1', function () {
    $job = screeningJob();

    expect(fn () => ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => 'PHP',
        'weight' => 0,
    ]))->toThrow(InvalidArgumentException::class);
});

it('keeps label_active_key in sync with a normalized label while active', function () {
    $job = screeningJob();

    $c = ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => '  PHP  ',
    ]);

    expect($c->fresh()->label_active_key)->toBe('php');
});

it('clears label_active_key on soft delete and allows re-creating the label', function () {
    $job = screeningJob();

    $c = ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => 'PHP',
    ]);
    $c->delete();

    $trashed = ApplicantScreeningCriteria::withTrashed()->find($c->id);
    expect($trashed->label_active_key)->toBeNull();

    $again = ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => 'PHP',
    ]);

    expect($again->label_active_key)->toBe('php');
});

it('syncs override active_marker with is_active', function () {
    $job = screeningJob();
    $applicant = Applicant::factory()->forJobPosting($job)->create();

    $o = ApplicantScreeningOverride::create([
        'applicant_id' => $applicant->id,
        'job_posting_id' => $job->id,
        'overridden_recommendation' => 'strong_match',
        'override_note' => 'HR judgement.',
        'is_active' => true,
    ]);
    expect($o->active_marker)->toBe(1);

    $o->is_active = false;
    $o->save();
    expect($o->fresh()->active_marker)->toBeNull();
});
