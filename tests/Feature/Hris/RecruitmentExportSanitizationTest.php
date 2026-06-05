<?php

use App\Exports\RecruitmentReportExport;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;

it('sanitizes a formula-injection job title in the recruitment export', function () {
    $job = JobPosting::factory()->create([
        'title' => '=HYPERLINK("http://evil","click")',
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);

    $row = (new RecruitmentReportExport(now()->subMonth(), now()))
        ->map($job->load(['department', 'applicants']));

    expect($row[1])->toStartWith("'="); // Title column is escaped
});
