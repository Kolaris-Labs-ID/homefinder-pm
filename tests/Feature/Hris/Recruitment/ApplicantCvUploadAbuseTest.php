<?php

use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RecruitmentAtsTestFactory as Ats;

beforeEach(function () {
    Storage::fake('private');
    Queue::fake();
    $this->withHeaders(['Accept' => 'application/json'])
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.123']);
    $this->job = Ats::publicJob();
});

it('rate-limits the public apply endpoint', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
            'email' => "rate-{$i}@example.test",
            'resume' => Ats::pdfUpload("cv-{$i}.pdf"),
        ]))->assertCreated();
    }

    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'email' => 'rate-6@example.test',
        'resume' => Ats::pdfUpload('cv-6.pdf'),
    ]))->assertTooManyRequests();
});

it('does not dispatch screening jobs for repeated invalid CV uploads', function () {
    for ($i = 1; $i <= 3; $i++) {
        $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
            'email' => "invalid-{$i}@example.test",
            'resume' => Ats::pdfUpload("fake-{$i}.pdf", 'not a pdf'),
        ]))->assertStatus(422);
    }

    expect(Applicant::count())->toBe(0);
    Queue::assertNotPushed(ScreenApplicantJob::class);
});

it('does not dispatch a second screening job for duplicate applications', function () {
    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'email' => 'duplicate@example.test',
        'resume' => Ats::pdfUpload('first.pdf'),
    ]))->assertCreated();

    $this->post("/api/public/v1/jobs/{$this->job->slug}/apply", Ats::applyPayload([
        'email' => ' DUPLICATE@example.test ',
        'resume' => Ats::pdfUpload('second.pdf'),
    ]))->assertStatus(422);

    Queue::assertPushed(ScreenApplicantJob::class, 1);
});
