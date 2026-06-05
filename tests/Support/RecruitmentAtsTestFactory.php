<?php

namespace Tests\Support;

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitmentAtsTestFactory
{
    public static function fixtureText(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/recruitment/cv_text/{$name}.txt"));
    }

    public static function fixturePdf(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/recruitment/cvs/{$name}.pdf"));
    }

    public static function pdfUpload(string $name = 'cv.pdf', ?string $content = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content ?? self::fixturePdf('valid_accounting_cv'));
    }

    public static function publicJob(array $overrides = []): JobPosting
    {
        return JobPosting::factory()->publicPortal()->create(array_merge([
            'slug' => 'ats-accounting',
            'title' => 'Accounting Staff',
            'hiring_company_id' => HiringCompany::factory()->create()->id,
            'salary_hidden' => false,
            'salary_min' => 8000000,
            'salary_max' => 10000000,
        ], $overrides));
    }

    public static function applyPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Synthetic Candidate',
            'email' => 'synthetic.candidate@example.test',
            'phone' => '08123456789',
            'cover_letter' => 'Synthetic application for testing.',
            'expected_salary' => 9000000,
            'consent_privacy_policy' => true,
            'consent_data_processing' => true,
            'website' => '',
        ], $overrides);
    }

    public static function storedResumeApplicant(JobPosting $job, array $overrides = [], string $content = '%PDF-1.4 synthetic cv'): Applicant
    {
        $path = "recruitment/resumes/{$job->id}/cv-".Str::uuid()->toString().'.pdf';
        Storage::disk('private')->put($path, $content);

        return Applicant::factory()->forJobPosting($job)->create(array_merge([
            'resume_disk' => 'private',
            'resume_file_path' => $path,
            'resume_original_filename' => 'synthetic-cv.pdf',
            'resume_mime_type' => 'application/pdf',
            'resume_size_bytes' => strlen($content),
            'resume_file_hash' => hash('sha256', $content),
            'status' => Applicant::STATUS_APPLIED,
        ], $overrides));
    }

    /**
     * @param  string[]  $keywords
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function criterion(int $id, string $group, string $label, array $keywords, int $weight = 1, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'group' => $group,
            'label' => $label,
            'keywords' => $keywords,
            'synonyms' => [],
            'weight' => $weight,
            'is_active' => true,
            'missing_means_failed' => false,
        ], $overrides);
    }

    /**
     * @param  string[]  $keywords
     * @param  array<string, mixed>  $overrides
     */
    public static function createCriterion(JobPosting $job, string $group, string $label, array $keywords, int $weight = 1, array $overrides = []): ApplicantScreeningCriteria
    {
        return ApplicantScreeningCriteria::create(array_merge([
            'job_posting_id' => $job->id,
            'group' => $group,
            'label' => $label,
            'keywords' => $keywords,
            'synonyms' => [],
            'weight' => $weight,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Criteria set with non-empty scoring groups. All weights are 1 unless a test
     * deliberately overrides them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function accountingCriteria(): array
    {
        return [
            self::criterion(1, 'must_have', 'Accounting', ['accounting']),
            self::criterion(2, 'must_have', 'Tax', ['tax']),
            self::criterion(3, 'nice_to_have', 'Bookkeeping', ['bookkeeping']),
            self::criterion(4, 'nice_to_have', 'Vendor invoice', ['vendor invoice']),
            self::criterion(5, 'fit', 'Jakarta location', ['jakarta']),
        ];
    }
}
