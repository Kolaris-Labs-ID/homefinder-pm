<?php

namespace App\Services\Hris;

use App\Exceptions\DuplicateApplicationException;
use App\Models\Hris\Applicant;
use App\Models\Hris\JobPosting;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobApplicationService
{
    public const CONSENT_POLICY_VERSION = '2026-01-v1';

    public const RETENTION_MONTHS = 12;

    public function __construct(private ResumeStorageService $resumeStorage) {}

    /**
     * Create a public job application.
     *
     * Ordering matters for partial-failure safety:
     *  1. Insert the applicant first — the DB unique (job, normalized_email)
     *     rejects duplicates before any file is written (no orphan CV).
     *  2. Store the CV; on any failure, roll back the applicant AND delete the file.
     */
    public function apply(JobPosting $job, array $data, UploadedFile $resume, ?string $ip, ?string $userAgent): Applicant
    {
        DB::beginTransaction();

        try {
            $applicant = new Applicant([
                'job_posting_id' => $job->id,
                'name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'cover_letter' => $data['cover_letter'] ?? null,
                'source' => Applicant::SOURCE_JOB_PORTAL,
                'status' => Applicant::STATUS_APPLIED,
                'consent_privacy_policy' => true,
                'consent_data_processing' => true,
                'consent_policy_version' => self::CONSENT_POLICY_VERSION,
                'consent_accepted_at' => now(),
                'applied_ip' => $ip,
                'applied_user_agent' => $userAgent,
                'retention_until' => now()->addMonths(self::RETENTION_MONTHS),
                'application_reference_code' => $this->generateReferenceCode(),
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'portfolio_url' => $data['portfolio_url'] ?? null,
                'expected_salary' => $data['expected_salary'] ?? null,
                'available_start_date' => $data['available_start_date'] ?? null,
            ]);
            $applicant->save();
        } catch (QueryException $e) {
            DB::rollBack();

            if ($this->alreadyApplied($job, $data['email'])) {
                throw new DuplicateApplicationException;
            }

            throw $e;
        }

        $meta = null;
        try {
            $meta = $this->resumeStorage->store($resume, $job);
            $applicant->forceFill($meta)->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->resumeStorage->delete($meta['resume_disk'] ?? null, $meta['resume_file_path'] ?? null);
            throw $e;
        }

        $this->notifyRecruiters($applicant);

        return $applicant;
    }

    private function alreadyApplied(JobPosting $job, string $email): bool
    {
        return Applicant::where('job_posting_id', $job->id)
            ->where('normalized_email', Applicant::normalizeEmail($email))
            ->exists();
    }

    private function generateReferenceCode(): string
    {
        do {
            $code = 'PH-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Applicant::where('application_reference_code', $code)->exists());

        return $code;
    }

    /**
     * Best-effort recruiter notification. A failure here must never fail the application.
     */
    private function notifyRecruiters(Applicant $applicant): void
    {
        try {
            $recruiters = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'hr-admin', 'hr-staff']))
                ->get();

            foreach ($recruiters as $user) {
                FilamentNotification::make()
                    ->title('New job application')
                    ->body($applicant->name.' applied for '.optional($applicant->jobPosting)->title)
                    ->sendToDatabase($user);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify recruiters of new application', ['error' => $e->getMessage()]);
        }
    }
}
