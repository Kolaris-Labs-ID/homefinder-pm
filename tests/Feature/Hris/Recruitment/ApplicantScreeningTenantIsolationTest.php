<?php

use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('documents the current single-tenant assumption and preserves hiring company boundary', function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    $job = Ats::publicJob(['slug' => 'company-boundary']);
    Ats::createCriterion($job, 'must_have', 'Accounting', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job);

    $result = app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    expect(config('filament-shield.tenant_model'))->toBeNull()
        ->and($result->hiring_company_id)->toBe($job->hiring_company_id)
        ->and($applicant->resume_file_path)->toStartWith("recruitment/resumes/{$job->id}/")
        ->and(ApplicantScreeningResult::where('job_posting_id', $job->id)->count())->toBe(1)
        ->and(ApplicantScreeningLatestResult::where('job_posting_id', $job->id)->count())->toBe(1);
});
