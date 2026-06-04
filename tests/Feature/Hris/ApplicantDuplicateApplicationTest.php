<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\JobPosting;
use Illuminate\Database\QueryException;

it('blocks a duplicate application to the same job via DB constraint', function () {
    $job = JobPosting::factory()->create();

    Applicant::factory()->create(['job_posting_id' => $job->id, 'email' => 'dup@example.com']);
    Applicant::factory()->create(['job_posting_id' => $job->id, 'email' => 'dup@example.com']);
})->throws(QueryException::class);

it('blocks a duplicate regardless of email casing/whitespace', function () {
    $job = JobPosting::factory()->create();

    Applicant::factory()->create(['job_posting_id' => $job->id, 'email' => 'dup@example.com']);
    Applicant::factory()->create(['job_posting_id' => $job->id, 'email' => '  DUP@Example.com ']);
})->throws(QueryException::class);

it('allows the same email to apply to different jobs', function () {
    $jobA = JobPosting::factory()->create();
    $jobB = JobPosting::factory()->create();

    Applicant::factory()->create(['job_posting_id' => $jobA->id, 'email' => 'multi@example.com']);
    $second = Applicant::factory()->create(['job_posting_id' => $jobB->id, 'email' => 'multi@example.com']);

    expect($second->exists)->toBeTrue();
    expect(Applicant::where('normalized_email', 'multi@example.com')->count())->toBe(2);
});

it('defaults source to manual_hr and consent to false', function () {
    $a = Applicant::factory()->create();

    expect($a->source)->toBe('manual_hr');
    expect($a->consent_privacy_policy)->toBeFalse();
    expect($a->consent_data_processing)->toBeFalse();
});
