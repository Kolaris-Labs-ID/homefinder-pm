<?php

use App\Filament\Resources\ApplicantResource\Pages\ViewApplicant;
use App\Models\Hris\Applicant;
use App\Models\User;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Database\Seeders\RecruitmentScreeningPermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
});

it('escapes a malicious original filename in resume download headers', function () {
    $job = Ats::publicJob();
    $applicant = Ats::storedResumeApplicant($job, [
        'resume_original_filename' => 'evil" onmouseover="alert(1).pdf',
    ], Ats::fixturePdf('malicious_filename_fixture'));
    $admin = tap(User::factory()->create())->assignRole('hr-admin');

    $response = $this->actingAs($admin)
        ->get(route('recruitment.resume.download', $applicant));

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))
        ->toContain('filename="evil\\" onmouseover=\\"alert(1).pdf"')
        ->not->toContain('filename="evil" onmouseover="alert(1).pdf"');
});

it('keeps attacker CV markup inert in the stored summary and does not change applicant status', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning(
        "Accounting experience.\n<img src=x onerror=alert(1)>",
    ));
    $job = Ats::publicJob(['slug' => 'xss-data']);
    Ats::createCriterion($job, 'must_have', 'Accounting', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job);

    $result = app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    // The stored summary keeps the raw text inert — escaping is the render layer's job,
    // and screening never mutates the applicant's pipeline status.
    expect($result->parsed_summary)->toContain('<img src=x onerror=alert(1)>')
        ->and($applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
});

it('escapes attacker CV text in the Filament screening summary so no script executes', function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning(
        "Accounting experience.\n<script>alert(document.cookie)</script>",
    ));
    $job = Ats::publicJob(['slug' => 'xss-summary']);
    Ats::createCriterion($job, 'must_have', 'Accounting', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job);
    app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    $this->actingAs(tap(User::factory()->create())->assignRole('hr-admin'));

    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->mountAction('viewScreening')
        ->assertDontSee('<script>alert(document.cookie)</script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('escapes attacker text in a matched criteria label in the Filament screening render', function () {
    $this->seed(RecruitmentScreeningPermissionSeeder::class);
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning('accounting'));
    $job = Ats::publicJob(['slug' => 'xss-label']);
    Ats::createCriterion($job, 'must_have', 'Accounting <script>alert(2)</script>', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job);
    app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    $this->actingAs(tap(User::factory()->create())->assignRole('hr-admin'));

    Livewire::test(ViewApplicant::class, ['record' => $applicant->getKey()])
        ->mountAction('viewScreening')
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertSee('Accounting &lt;script&gt;', false);
});
