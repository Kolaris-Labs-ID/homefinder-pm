<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Support\Carbon;

function makePublicJob(array $overrides = []): JobPosting
{
    $company = $overrides['company'] ?? HiringCompany::factory()->create();
    unset($overrides['company']);

    return JobPosting::factory()->publicPortal()->create(array_merge([
        'hiring_company_id' => $company->id,
        'salary_hidden' => true,
        'application_deadline' => null,
    ], $overrides));
}

it('lists a published, public job', function () {
    makePublicJob(['public_title' => 'Senior Laravel Engineer']);

    $res = $this->getJson('/api/public/v1/jobs');

    $res->assertOk();
    expect($res->json('meta.total'))->toBe(1);
    expect($res->json('data.0.title'))->toBe('Senior Laravel Engineer');
});

it('hides draft, closed, expired, and inactive-company jobs', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00', 'Asia/Jakarta'));
    makePublicJob(['status' => JobPosting::STATUS_DRAFT]);
    makePublicJob(['status' => JobPosting::STATUS_CLOSED]);
    makePublicJob(['application_deadline' => '2026-06-03']);
    makePublicJob(['company' => HiringCompany::factory()->inactive()->create()]);
    makePublicJob(['is_public' => false]);

    $res = $this->getJson('/api/public/v1/jobs');

    expect($res->json('meta.total'))->toBe(0);
    Carbon::setTestNow();
});

it('omits salary when hidden and includes it when shown', function () {
    makePublicJob(['salary_hidden' => true, 'salary_min' => 5000000, 'salary_max' => 9000000, 'public_title' => 'Hidden Pay']);

    $hidden = $this->getJson('/api/public/v1/jobs')->json('data.0');
    expect($hidden)->not->toHaveKey('salary_min');
    expect($hidden)->not->toHaveKey('salary_max');

    makePublicJob(['salary_hidden' => false, 'salary_min' => 5000000, 'salary_max' => 9000000]);
    $items = $this->getJson('/api/public/v1/jobs')->json('data');
    $shown = collect($items)->firstWhere('salary_min', 5000000);
    expect($shown['salary_max'])->toBe(9000000);
});

it('never leaks internal fields', function () {
    $job = makePublicJob();
    Applicant::factory()->create(['job_posting_id' => $job->id]);

    $item = $this->getJson('/api/public/v1/jobs')->json('data.0');

    foreach (['department_id', 'hiring_company_id', 'resume_file_path', 'hired_employee_id', 'is_public', 'applicants', 'status'] as $leak) {
        expect($item)->not->toHaveKey($leak);
    }
});

it('paginates the listing', function () {
    JobPosting::factory()->count(3)->publicPortal()->create();

    $res = $this->getJson('/api/public/v1/jobs?per_page=2');

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.total'))->toBe(3);
    expect($res->json('meta.last_page'))->toBe(2);
});

it('filters by employment type and work arrangement', function () {
    makePublicJob(['employment_type' => JobPosting::EMPLOYMENT_TYPE_INTERN, 'work_arrangement' => JobPosting::WORK_ARRANGEMENT_REMOTE, 'public_title' => 'Remote Intern']);
    makePublicJob(['employment_type' => JobPosting::EMPLOYMENT_TYPE_FULL_TIME, 'work_arrangement' => JobPosting::WORK_ARRANGEMENT_ONSITE]);

    $res = $this->getJson('/api/public/v1/jobs?employment_type=intern&work_arrangement=remote');

    expect($res->json('meta.total'))->toBe(1);
    expect($res->json('data.0.title'))->toBe('Remote Intern');
});

it('returns a public job detail by slug', function () {
    $job = makePublicJob(['slug' => 'senior-engineer', 'public_description' => 'Build great things.']);

    $res = $this->getJson('/api/public/v1/jobs/senior-engineer');

    $res->assertOk();
    expect($res->json('data.slug'))->toBe('senior-engineer');
    expect($res->json('data.description'))->toBe('Build great things.');
});

it('returns 404 for a non-public job slug', function () {
    JobPosting::factory()->draft()->create(['slug' => 'secret-role']);

    $this->getJson('/api/public/v1/jobs/secret-role')->assertNotFound();
});

it('returns a company profile and its jobs', function () {
    $company = HiringCompany::factory()->create(['slug' => 'acme', 'name' => 'Acme Inc']);
    makePublicJob(['company' => $company, 'public_title' => 'Acme Role']);

    $profile = $this->getJson('/api/public/v1/companies/acme');
    $profile->assertOk();
    expect($profile->json('data.name'))->toBe('Acme Inc');

    $jobs = $this->getJson('/api/public/v1/companies/acme/jobs');
    expect($jobs->json('meta.total'))->toBe(1);
    expect($jobs->json('data.0.title'))->toBe('Acme Role');
});
