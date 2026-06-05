<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningResult;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\FakeResumeTextExtractor;
use App\Services\Hris\Screening\Extractors\PopplerResumeTextExtractor;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    $this->job = Ats::publicJob();
    Ats::createCriterion($this->job, 'must_have', 'Accounting', ['accounting']);
});

it('extracts key tokens from readable PDF text through the parser wrapper', function () {
    $extractor = new PopplerResumeTextExtractor(
        rawExtractor: fn (string $path): string => 'Synthetic accounting CV with tax and reconciliation tokens.',
        maxChars: 1000,
    );

    $text = $extractor->extract(base_path('tests/Fixtures/recruitment/cvs/valid_accounting_cv.pdf'));

    expect($text)->toContain('accounting')
        ->and($text)->toContain('tax');
});

it('records empty, image-only, or password-protected PDF extraction as manual review without changing applicant status', function () {
    foreach (['pdf_text_empty', 'pdf_text_empty', 'password_protected'] as $reason) {
        $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::failing($reason));
        $applicant = Ats::storedResumeApplicant($this->job);

        $result = app(ScreeningOrchestrator::class)->screen($applicant, $this->job, (string) Str::ulid());

        expect($result->parser_status)->toBe(ApplicantScreeningResult::PARSER_FAILED)
            ->and($result->score)->toBeNull()
            ->and($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW)
            ->and($result->parser_failure_reason)->toBe($reason)
            ->and($applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
    }
});

it('limits parsed text length before matching stores a redacted summary', function () {
    $extractor = new PopplerResumeTextExtractor(
        rawExtractor: fn (string $path): string => str_repeat('accounting ', 100),
        maxChars: 25,
    );

    expect(mb_strlen($extractor->extract('synthetic.pdf')))->toBe(25);
});

it('parser failure creates only a screening result and never mutates applicant status', function () {
    $this->app->instance(ResumeTextExtractor::class, FakeResumeTextExtractor::failing('pdf_text_extraction_failed'));
    $applicant = Ats::storedResumeApplicant($this->job, ['status' => Applicant::STATUS_APPLIED]);

    $result = app(ScreeningOrchestrator::class)->screen($applicant, $this->job, (string) Str::ulid());

    expect($result->recommendation)->toBe(ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW)
        ->and($applicant->fresh()->status)->toBe(Applicant::STATUS_APPLIED);
});
