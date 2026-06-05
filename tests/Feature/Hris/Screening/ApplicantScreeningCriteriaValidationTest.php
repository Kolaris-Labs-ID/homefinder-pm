<?php

use App\Exceptions\SensitiveScreeningCriteriaException;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;

function criteriaJob(): JobPosting
{
    return JobPosting::factory()->create([
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
}

it('blocks a sensitive term used as the label', function (string $term) {
    $job = criteriaJob();

    expect(fn () => ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => $term,
    ]))->toThrow(SensitiveScreeningCriteriaException::class);
})->with(['agama', 'jenis kelamin', 'umur', 'usia', 'NIK', 'tanggal lahir', 'foto', 'gender', 'marital status', 'date of birth']);

it('blocks a sensitive term hidden in keywords', function () {
    $job = criteriaJob();

    expect(fn () => ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => 'Background',
        'keywords' => ['php', 'agama islam'],
    ]))->toThrow(SensitiveScreeningCriteriaException::class);
});

it('blocks a sensitive term hidden in synonyms', function () {
    $job = criteriaJob();

    expect(fn () => ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'fit',
        'label' => 'Region',
        'synonyms' => ['usia'],
    ]))->toThrow(SensitiveScreeningCriteriaException::class);
});

it('allows clean professional criteria', function () {
    $job = criteriaJob();

    $c = ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'must_have',
        'label' => 'Laravel',
        'keywords' => ['laravel', 'php'],
        'synonyms' => ['symfony'],
    ]);

    expect($c->exists)->toBeTrue();
});

it('does not false-positive on words that merely contain a sensitive substring', function () {
    $job = criteriaJob();

    // "manager"/"storage" contain "age"; "teknik" contains "nik" — none may be blocked.
    $c = ApplicantScreeningCriteria::create([
        'job_posting_id' => $job->id,
        'group' => 'nice_to_have',
        'label' => 'Manager',
        'keywords' => ['teknik industri', 'storage'],
    ]);

    expect($c->exists)->toBeTrue();
});
