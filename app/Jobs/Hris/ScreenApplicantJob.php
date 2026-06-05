<?php

namespace App\Jobs\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\JobPosting;
use App\Models\User;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one screening orchestration on the dedicated `screening` queue. Bounded
 * resources: 3 tries, 30s timeout, staged backoff. The orchestrator is idempotent on
 * screening_run_id, so a redelivered retry never produces a duplicate version.
 */
class ScreenApplicantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 30, 60];

    public int $maxExceptions = 3;

    public function __construct(
        public int $applicantId,
        public int $jobPostingId,
        public string $screeningRunId,
        public ?int $screenedById = null,
        public bool $manual = false,
    ) {
        $this->onQueue('screening');
    }

    public function handle(ScreeningOrchestrator $orchestrator): void
    {
        $applicant = Applicant::find($this->applicantId);
        $job = JobPosting::find($this->jobPostingId);

        if ($applicant === null || $job === null) {
            return; // applicant or posting removed before the job ran
        }

        $by = $this->screenedById !== null ? User::find($this->screenedById) : null;

        $orchestrator->screen($applicant, $job, $this->screeningRunId, $by, $this->manual);
    }

    public function failed(?Throwable $e): void
    {
        // No pending placeholder rows exist, so a failed run simply leaves the applicant
        // "not screened" (re-runnable by HR) rather than stuck pending.
        Log::warning('Applicant screening job failed', [
            'applicant_id' => $this->applicantId,
            'job_posting_id' => $this->jobPostingId,
            'screening_run_id' => $this->screeningRunId,
            'error' => $e?->getMessage(),
        ]);
    }
}
