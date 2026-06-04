<?php

use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Support\Carbon;

function publicJob(array $overrides = []): JobPosting
{
    $company = $overrides['company'] ?? HiringCompany::factory()->create();
    unset($overrides['company']);

    return JobPosting::factory()->published()->create(array_merge([
        'hiring_company_id' => $company->id,
        'is_public' => true,
        'salary_hidden' => true,
        'application_deadline' => null,
        'published_public_at' => now(),
    ], $overrides));
}

it('shows a published, public job from an active company', function () {
    publicJob();
    expect(JobPosting::publiclyVisible()->count())->toBe(1);
});

it('hides draft jobs', function () {
    publicJob(['status' => JobPosting::STATUS_DRAFT, 'is_public' => true]);
    expect(JobPosting::publiclyVisible()->count())->toBe(0);
});

it('hides closed jobs', function () {
    publicJob(['status' => JobPosting::STATUS_CLOSED]);
    expect(JobPosting::publiclyVisible()->count())->toBe(0);
});

it('hides jobs that are not flagged public', function () {
    publicJob(['is_public' => false]);
    expect(JobPosting::publiclyVisible()->count())->toBe(0);
});

it('hides jobs from inactive companies', function () {
    $inactive = HiringCompany::factory()->inactive()->create();
    publicJob(['company' => $inactive]);
    expect(JobPosting::publiclyVisible()->count())->toBe(0);
});

it('hides jobs whose deadline has passed (Asia/Jakarta)', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Asia/Jakarta'));
    publicJob(['application_deadline' => '2026-06-03']);
    expect(JobPosting::publiclyVisible()->count())->toBe(0);
    Carbon::setTestNow();
});

it('shows jobs whose deadline is today or future (Asia/Jakarta)', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00', 'Asia/Jakarta'));
    publicJob(['application_deadline' => '2026-06-04']);
    publicJob(['application_deadline' => '2026-06-10']);
    expect(JobPosting::publiclyVisible()->count())->toBe(2);
    Carbon::setTestNow();
});

it('auto-generates a unique slug', function () {
    $a = JobPosting::factory()->create(['title' => 'Senior Engineer', 'slug' => null]);
    $b = JobPosting::factory()->create(['title' => 'Senior Engineer', 'slug' => null]);

    expect($a->slug)->toBe('senior-engineer');
    expect($b->slug)->not->toBe($a->slug);
});

it('omits salary entirely when hidden', function () {
    $job = publicJob(['salary_hidden' => true, 'salary_min' => 5000000, 'salary_max' => 9000000]);
    expect($job->publicSalary())->toBe([]);
});

it('exposes salary when not hidden', function () {
    $job = publicJob(['salary_hidden' => false, 'salary_min' => 5000000, 'salary_max' => 9000000]);
    expect($job->publicSalary())->toBe(['salary_min' => 5000000, 'salary_max' => 9000000]);
});
