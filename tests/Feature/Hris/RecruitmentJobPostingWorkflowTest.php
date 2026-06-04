<?php

use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use App\Services\Hris\RecruitmentService;

beforeEach(function () {
    $this->service = new RecruitmentService;
});

it('publishes a draft job posting', function () {
    $posting = JobPosting::factory()->draft()->create();
    $this->service->publishJobPosting($posting);

    expect($posting->fresh()->status)->toBe(JobPosting::STATUS_PUBLISHED);
    expect($posting->fresh()->published_at)->not->toBeNull();
});

it('cannot publish a non-draft posting', function () {
    $posting = JobPosting::factory()->published()->create();
    $this->service->publishJobPosting($posting);
})->throws(Exception::class);

it('closes a published posting', function () {
    $posting = JobPosting::factory()->published()->create();
    $this->service->closeJobPosting($posting);

    expect($posting->fresh()->status)->toBe(JobPosting::STATUS_CLOSED);
});

it('publishes to the public portal when linked to a company', function () {
    $company = HiringCompany::factory()->create();
    $posting = JobPosting::factory()->published()->create(['hiring_company_id' => $company->id]);

    $this->service->publishJobPostingPublicly($posting);

    $posting->refresh();
    expect($posting->is_public)->toBeTrue();
    expect($posting->published_public_at)->not->toBeNull();
});

it('refuses public publish without a hiring company', function () {
    $posting = JobPosting::factory()->published()->create(['hiring_company_id' => null]);
    $this->service->publishJobPostingPublicly($posting);
})->throws(Exception::class);

it('refuses public publish when not internally published', function () {
    $company = HiringCompany::factory()->create();
    $posting = JobPosting::factory()->draft()->create(['hiring_company_id' => $company->id]);
    $this->service->publishJobPostingPublicly($posting);
})->throws(Exception::class);

it('unpublishes from the public portal', function () {
    $posting = JobPosting::factory()->publicPortal()->create();
    $this->service->unpublishJobPostingPublicly($posting);

    $posting->refresh();
    expect($posting->is_public)->toBeFalse();
    expect($posting->closed_public_at)->not->toBeNull();
});
