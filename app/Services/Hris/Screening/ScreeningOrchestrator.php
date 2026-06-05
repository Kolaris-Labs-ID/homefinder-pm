<?php

namespace App\Services\Hris\Screening;

use App\Exceptions\ResumeParseException;
use App\Models\Hris\Applicant;
use App\Models\Hris\ApplicantScreeningLatestResult;
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
    ) {}

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

            $attributes = $this->successAttributes($match, $snapshot) + $this->baseAttributes($applicant, $job, $runId, $by, $referenceDate, $snapshot);
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
            'parsed_summary' => null, // populated (redacted) by the ResumeRedactor task
            'redacted_fields' => [],
            'screened_by' => $by?->id,
            'screened_at' => now(),
        ];
    }

    private function successAttributes(Dto\MatchResultDto $match, CriteriaSnapshotDto $snapshot): array
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
