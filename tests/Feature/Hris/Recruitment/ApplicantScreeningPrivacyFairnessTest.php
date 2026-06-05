<?php

use App\Exceptions\SensitiveScreeningCriteriaException;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningCriteria;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\RequirementMatcher;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

it('scores gender, religion, age, and marital-status counterfactuals the same', function () {
    $matcher = new RequirementMatcher;
    $criteria = [
        Ats::criterion(1, 'must_have', 'Accounting', ['accounting']),
        Ats::criterion(2, 'must_have', 'Tax', ['tax']),
    ];

    $pairs = [
        [Ats::fixtureText('sensitive_counterfactual_male'), Ats::fixtureText('sensitive_counterfactual_female')],
        [Ats::fixtureText('sensitive_counterfactual_religion_a'), Ats::fixtureText('sensitive_counterfactual_religion_b')],
        ['Age: 24. Skills: accounting tax.', 'Age: 54. Skills: accounting tax.'],
        ['Marital status: single. Skills: accounting tax.', 'Marital status: married. Skills: accounting tax.'],
    ];

    foreach ($pairs as [$left, $right]) {
        $a = $matcher->match($left, $criteria);
        $b = $matcher->match($right, $criteria);

        expect($a->score)->toBe($b->score)
            ->and($a->recommendation)->toBe($b->recommendation);
    }
});

it('rejects sensitive screening criteria terms at model level', function () {
    foreach (['agama', 'gender', 'age', 'status menikah', 'NIK'] as $index => $term) {
        $job = Ats::publicJob(['slug' => "sensitive-{$index}"]);

        expect(fn () => ApplicantScreeningCriteria::create([
            'job_posting_id' => $job->id,
            'group' => 'must_have',
            'label' => $term,
            'keywords' => [$term],
        ]))->toThrow(SensitiveScreeningCriteriaException::class);
    }
});

it('stores only a redacted summary and leaves applicant status unchanged', function () {
    Storage::fake('private');
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::returning(
        "Accounting tax.\nNIK: 3200000000000000\nAgama: synthetic\nTanggal lahir: 01-01-1990\nStatus menikah: synthetic\nAlamat: synthetic street",
    ));
    $job = Ats::publicJob();
    Ats::createCriterion($job, 'must_have', 'Accounting', ['accounting']);
    $applicant = Ats::storedResumeApplicant($job, ['status' => Applicant::STATUS_APPLIED]);

    $result = app(ScreeningOrchestrator::class)->screen($applicant, $job, (string) Str::ulid());

    expect($result->parsed_summary)->toContain('Accounting tax')
        ->and($result->parsed_summary)->not->toContain('3200000000000000')
        ->and($result->parsed_summary)->not->toContain('synthetic street')
        ->and($result->redacted_fields)->toContain('nik')
        ->and($result->redacted_fields)->toContain('full_address')
        ->and($applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
});

it('public application response omits full parsed text and screening internals while retention exists', function () {
    Storage::fake('private');
    Queue::fake();
    $this->withHeaders(['Accept' => 'application/json']);
    $job = Ats::publicJob(['slug' => 'privacy-apply']);

    $response = $this->post("/api/public/v1/jobs/{$job->slug}/apply", Ats::applyPayload([
        'email' => 'privacy@example.test',
        'resume' => Ats::pdfUpload(),
    ]));

    $response->assertCreated();
    $applicant = Applicant::firstOrFail();

    expect($applicant->retention_until)->not->toBeNull()
        ->and(json_encode($response->json()))->not->toContain('screening')
        ->and(json_encode($response->json()))->not->toContain('parsed');
});
