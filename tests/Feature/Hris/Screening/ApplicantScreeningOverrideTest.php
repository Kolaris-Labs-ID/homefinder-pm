<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningOverride;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Models\User;
use App\Services\Hris\Screening\ScreeningOrchestrator;

beforeEach(function () {
    $this->job = JobPosting::factory()->create(['hiring_company_id' => HiringCompany::factory()->create()->id]);
    $this->applicant = Applicant::factory()->forJobPosting($this->job)->create();
    $this->user = User::factory()->create();
});

it('creating an override replaces the prior active one, keeping exactly one active', function () {
    $orchestrator = app(ScreeningOrchestrator::class);

    $first = $orchestrator->override($this->applicant, 'strong_match', 'looks great', $this->user);
    $second = $orchestrator->override($this->applicant, 'low_match', 'reconsidered', $this->user);

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue()
        ->and(ApplicantScreeningOverride::where('applicant_id', $this->applicant->id)->where('is_active', true)->count())->toBe(1);
});

it('clearing an override deactivates it with an audited note and actor', function () {
    $orchestrator = app(ScreeningOrchestrator::class);

    $override = $orchestrator->override($this->applicant, 'strong_match', 'note', $this->user);
    $orchestrator->clearOverride($this->applicant, 'no longer valid', $this->user);

    expect($override->fresh()->is_active)->toBeFalse()
        ->and($override->fresh()->clear_note)->toBe('no longer valid')
        ->and($override->fresh()->cleared_by)->toBe($this->user->id);
});

it('does not change the underlying immutable result score', function () {
    $orchestrator = app(ScreeningOrchestrator::class);
    $override = $orchestrator->override($this->applicant, 'strong_match', 'note', $this->user);

    // override lives in its own table, not on any result row
    expect($override->applicant_id)->toBe($this->applicant->id)
        ->and($override->job_posting_id)->toBe($this->job->id);
});
