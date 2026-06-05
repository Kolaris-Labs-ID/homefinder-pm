<?php

use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    Queue::fake();
    $this->withHeaders(['Accept' => 'application/json']);
    $this->job = Ats::publicJob();
});

it('accepts a PDF under 1 MB and stores private randomized resume metadata', function () {
    $response = $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'resume' => Ats::pdfUpload('synthetic-accounting.pdf'),
    ]));

    $response->assertCreated();

    $applicant = Applicant::firstOrFail();

    expect($applicant->status)->toBe(Applicant::STATUS_APPLIED)
        ->and($applicant->resume_disk)->toBe('private')
        ->and($applicant->resume_original_filename)->toBe('synthetic-accounting.pdf')
        ->and($applicant->resume_mime_type)->toBe('application/pdf')
        ->and($applicant->resume_size_bytes)->toBeGreaterThan(0)
        ->and($applicant->resume_file_hash)->toBe(hash('sha256', Ats::fixturePdf('valid_accounting_cv')))
        ->and($applicant->resume_file_path)->toStartWith("recruitment/resumes/{$this->job->id}/")
        ->and($applicant->resume_file_path)->not->toContain('synthetic-accounting');

    Storage::disk('private')->assertExists($applicant->resume_file_path);
    Queue::assertPushed(ScreenApplicantJob::class, 1);

    $payload = json_encode($response->json());
    expect($payload)->not->toContain('resume_file_path')
        ->and($payload)->not->toContain('recruitment/resumes');
});

it('rejects a PDF over 1 MB and does not dispatch screening', function () {
    $largePdf = UploadedFile::fake()->createWithContent('large.pdf', '%PDF'.str_repeat('a', 1048576));

    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'resume' => $largePdf,
    ]))->assertStatus(422);

    expect(Applicant::count())->toBe(0);
    Queue::assertNotPushed(ScreenApplicantJob::class);
});

it('rejects DOC and DOCX uploads', function () {
    foreach ([['cv.doc', 'not a pdf'], ['cv.docx', 'PK'.str_repeat('x', 100)]] as [$filename, $content]) {
        $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
            'resume' => UploadedFile::fake()->createWithContent($filename, $content),
        ]))->assertStatus(422);
    }

    expect(Applicant::count())->toBe(0);
    Queue::assertNotPushed(ScreenApplicantJob::class);
});

it('does not trust a PDF extension or client MIME when magic bytes are wrong', function () {
    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'resume' => Ats::pdfUpload('fake.pdf', Ats::fixturePdf('fake_pdf_claimed_mime')),
    ]))->assertStatus(422);

    expect(Applicant::count())->toBe(0);
    Queue::assertNotPushed(ScreenApplicantJob::class);
});
