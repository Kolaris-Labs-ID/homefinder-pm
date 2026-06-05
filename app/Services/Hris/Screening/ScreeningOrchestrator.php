<?php

namespace App\Services\Hris\Screening;

use App\Exceptions\ResumeParseException;
use App\Jobs\Hris\ScreenApplicantJob;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningLatestResult;
use App\Models\Hris\ApplicantScreeningOverride;
use App\Models\Hris\ApplicantScreeningResult;
use App\Models\Hris\JobPosting;
use App\Models\User;
use App\Services\Hris\Screening\Contracts\ResumeTextExtractor;
use App\Services\Hris\Screening\Dto\CriteriaSnapshotDto;
use App\Services\Hris\Screening\Dto\FlagDto;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orchestrates one screening run. Parsing and matching happen OUTSIDE any DB
 * transaction; only version allocation + the immutable result insert + the latest
 * pointer upsert run inside a short transaction. Retry is idempotent via
 * screening_run_id; a manual re-run passes a new run id and gets a new version.
 */
class ScreeningOrchestrator
{
    public function __construct(
        private ResumeTextExtractor $extractor,
        private RequirementMatcher $matcher,
        private CriteriaSnapshotService $snapshots,
        private ResumeRedactor $redactor,
    ) {}

    /**
     * Erasure path for anonymize/forget. Results are immutable, so we DELETE the
     * screening artifacts (delete bypasses the update guard) rather than mutate them.
     */
    public function forgetApplicant(Applicant $applicant): void
    {
        ApplicantScreeningLatestResult::where('applicant_id', $applicant->id)->delete();
        ApplicantScreeningOverride::where('applicant_id', $applicant->id)->delete();
        ApplicantScreeningResult::where('applicant_id', $applicant->id)->delete();
    }

    /** Dispatch a (re-)screen from HR. A new run id => a new version. */
    public function runScreening(Applicant $applicant, ?User $by = null, bool $manual = true): void
    {
        ScreenApplicantJob::dispatch($applicant->id, $applicant->job_posting_id, (string) Str::ulid(), $by?->id, $manual);
    }

    /**
     * HR override on the (applicant, job) pair. Supersedes any active override (kept as a
     * cleared audit row) and never touches the immutable result or its raw score.
     */
    public function override(Applicant $applicant, string $recommendation, string $note, User $by): ApplicantScreeningOverride
    {
        return DB::transaction(function () use ($applicant, $recommendation, $note, $by) {
            $this->deactivateActiveOverride($applicant, $by, 'Superseded by a new override.');

            return ApplicantScreeningOverride::create([
                'applicant_id' => $applicant->id,
                'job_posting_id' => $applicant->job_posting_id,
                'overridden_recommendation' => $recommendation,
                'override_note' => $note,
                'created_by' => $by->id,
            ]);
        });
    }

    public function clearOverride(Applicant $applicant, string $note, User $by): void
    {
        $this->deactivateActiveOverride($applicant, $by, $note);
    }

    private function deactivateActiveOverride(Applicant $applicant, User $by, string $note): void
    {
        // Mass update bypasses model events, so set active_marker explicitly to free the
        // one-active slot (NULL is distinct in the unique index).
        ApplicantScreeningOverride::where('applicant_id', $applicant->id)
            ->where('job_posting_id', $applicant->job_posting_id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'active_marker' => null,
                'cleared_by' => $by->id,
                'cleared_at' => now(),
                'clear_note' => $note,
            ]);
    }

    public function screen(Applicant $applicant, JobPosting $job, string $runId, ?User $by = null, bool $manual = false): ApplicantScreeningResult
    {
        // Retry idempotency: a result already exists for this run id.
        if ($existing = ApplicantScreeningResult::where('screening_run_id', $runId)->first()) {
            return $existing;
        }

        $referenceDate = ($applicant->created_at ?? now())->copy()->startOfDay();
        $snapshot = $this->snapshots->snapshot($job);

        try {
            // Legacy applicants without a verified hash must not be auto-screened.
            if (blank($applicant->resume_file_hash)) {
                throw new ResumeParseException('hash_missing', 'Resume hash missing; cannot screen safely.');
            }

            $text = $this->extractCv($applicant); // parse OUTSIDE the transaction

            $match = $this->matcher->match( // match OUTSIDE the transaction
                $text,
                $snapshot->criteria,
                $referenceDate,
                $this->fitContext($applicant, $job),
            );

            $attributes = $this->successAttributes($match, $this->redactor->redact($text)) + $this->baseAttributes($applicant, $job, $runId, $by, $referenceDate, $snapshot);
        } catch (ResumeParseException $e) {
            $attributes = $this->failureAttributes($e->reason) + $this->baseAttributes($applicant, $job, $runId, $by, $referenceDate, $snapshot);
        }

        try {
            return $this->persist($applicant, $job, $attributes);
        } catch (QueryException $e) {
            // A concurrent worker inserted this run id first.
            if ($won = ApplicantScreeningResult::where('screening_run_id', $runId)->first()) {
                return $won;
            }
            throw $e;
        }
    }

    private function persist(Applicant $applicant, JobPosting $job, array $attributes): ApplicantScreeningResult
    {
        return DB::transaction(function () use ($applicant, $job, $attributes) {
            $version = ((int) ApplicantScreeningResult::query()
                ->where('applicant_id', $applicant->id)
                ->where('job_posting_id', $job->id)
                ->lockForUpdate()
                ->max('version')) + 1;

            $result = ApplicantScreeningResult::create($attributes + ['version' => $version]);

            ApplicantScreeningLatestResult::updateOrCreate(
                ['applicant_id' => $applicant->id, 'job_posting_id' => $job->id],
                ['latest_result_id' => $result->id, 'latest_version' => $version, 'updated_at' => now()],
            );

            return $result;
        });
    }

    private function baseAttributes(Applicant $applicant, JobPosting $job, string $runId, ?User $by, CarbonInterface $referenceDate, CriteriaSnapshotDto $snapshot): array
    {
        return [
            'screening_run_id' => $runId,
            'applicant_id' => $applicant->id,
            'job_posting_id' => $job->id,
            'hiring_company_id' => $job->hiring_company_id,
            'criteria_snapshot' => $snapshot->toArray(),
            'criteria_snapshot_hash' => $this->snapshots->hash($snapshot),
            'parser_version' => (string) config('screening.versions.parser'),
            'matcher_version' => (string) config('screening.versions.matcher'),
            'screening_version' => (string) config('screening.versions.screening'),
            'synonym_dictionary_version' => $snapshot->synonymDictionaryVersion,
            'screening_reference_date' => $referenceDate->toDateString(),
            'screened_by' => $by?->id,
            'screened_at' => now(),
        ];
    }

    private function successAttributes(Dto\MatchResultDto $match, array $redaction): array
    {
        return [
            'parser_status' => ApplicantScreeningResult::PARSER_PARSED,
            'parser_failure_reason' => null,
            'score' => $match->score,
            'recommendation' => $match->recommendation,
            'flags' => array_map(fn ($f) => $f->toArray(), $match->flags),
            'matched_criteria' => array_map(fn ($m) => $m->toArray(), $match->matched),
            'missing_criteria' => array_map(fn ($m) => $m->toArray(), $match->missing),
            'fit_outcomes' => array_map(fn ($f) => $f->toArray(), $match->fitOutcomes),
            'raw_scoring_breakdown' => $match->breakdown->toArray(),
            'parsed_summary' => $redaction['summary'],
            'redacted_fields' => $redaction['redacted'],
        ];
    }

    private function failureAttributes(string $reason): array
    {
        $flags = [];
        if ($reason === 'hash_missing') {
            $flags[] = (new FlagDto('hash_missing', FlagDto::SEVERITY_WARNING, 'Resume hash missing; applicant held for manual review.'))->toArray();
        }

        return [
            'parser_status' => ApplicantScreeningResult::PARSER_FAILED,
            'parser_failure_reason' => $reason,
            'score' => null,
            'recommendation' => ApplicantScreeningResult::REC_NEEDS_MANUAL_REVIEW,
            'flags' => $flags,
            'matched_criteria' => [],
            'missing_criteria' => [],
            'fit_outcomes' => [],
            'raw_scoring_breakdown' => [],
            'parsed_summary' => null,
            'redacted_fields' => [],
        ];
    }

    private function extractCv(Applicant $applicant): string
    {
        $disk = Storage::disk($applicant->resume_disk ?: 'private');

        if (blank($applicant->resume_file_path) || ! $disk->exists($applicant->resume_file_path)) {
            throw new ResumeParseException('resume_file_missing', 'Stored resume not found.');
        }

        $content = (string) $disk->get($applicant->resume_file_path);

        if (hash('sha256', $content) !== $applicant->resume_file_hash) {
            throw new ResumeParseException('resume_hash_mismatch', 'Resume changed after application.');
        }

        // Hand the extractor the stored file's real path (private disk is local); no
        // temp copy, so nothing to clean up.
        return $this->extractor->extract($disk->path($applicant->resume_file_path));
    }

    private function fitContext(Applicant $applicant, JobPosting $job): array
    {
        return [
            'salary_hidden' => (bool) $job->salary_hidden,
            'salary_max' => $job->salary_max,
            'expected_salary' => $applicant->expected_salary,
        ];
    }
}
