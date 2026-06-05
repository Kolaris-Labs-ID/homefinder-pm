<?php

use App\Models\Hris\Applicant;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function makeApplicantAndJob(): array
{
    $job = JobPosting::factory()->create([
        'hiring_company_id' => HiringCompany::factory()->create()->id,
    ]);
    $applicant = Applicant::factory()->forJobPosting($job)->create();

    return [$applicant, $job];
}

function insertResult(Applicant $a, JobPosting $job, int $version, string $runId): void
{
    DB::table('applicant_screening_results')->insert([
        'screening_run_id' => $runId,
        'applicant_id' => $a->id,
        'job_posting_id' => $job->id,
        'hiring_company_id' => $job->hiring_company_id,
        'version' => $version,
        'parser_status' => 'parsed',
        'parser_failure_reason' => null,
        'score' => 75.00,
        'recommendation' => 'review',
        'flags' => json_encode([]),
        'matched_criteria' => json_encode([]),
        'missing_criteria' => json_encode([]),
        'fit_outcomes' => json_encode([]),
        'raw_scoring_breakdown' => json_encode([]),
        'criteria_snapshot' => json_encode([]),
        'criteria_snapshot_hash' => str_repeat('0', 64),
        'parser_version' => '1',
        'matcher_version' => '1',
        'screening_version' => '1',
        'synonym_dictionary_version' => '1',
        'screening_reference_date' => $a->created_at->toDateString(),
        'parsed_summary' => null,
        'redacted_fields' => json_encode([]),
        'screened_by' => null,
        'screened_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertOverride(Applicant $a, JobPosting $job, ?int $activeMarker): void
{
    DB::table('applicant_screening_overrides')->insert([
        'applicant_id' => $a->id,
        'job_posting_id' => $job->id,
        'overridden_recommendation' => 'strong_match',
        'override_note' => 'HR judgement.',
        'is_active' => $activeMarker === 1,
        'active_marker' => $activeMarker,
        'created_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('creates the four screening tables', function () {
    expect(Schema::hasTable('applicant_screening_criteria'))->toBeTrue()
        ->and(Schema::hasTable('applicant_screening_results'))->toBeTrue()
        ->and(Schema::hasTable('applicant_screening_latest_results'))->toBeTrue()
        ->and(Schema::hasTable('applicant_screening_overrides'))->toBeTrue();
});

it('stores every immutable result audit column', function () {
    expect(Schema::hasColumns('applicant_screening_results', [
        'screening_run_id', 'applicant_id', 'job_posting_id', 'hiring_company_id', 'version',
        'parser_status', 'parser_failure_reason', 'score', 'recommendation', 'flags',
        'matched_criteria', 'missing_criteria', 'fit_outcomes', 'raw_scoring_breakdown',
        'criteria_snapshot', 'criteria_snapshot_hash', 'parser_version', 'matcher_version',
        'screening_version', 'synonym_dictionary_version', 'screening_reference_date',
        'parsed_summary', 'redacted_fields', 'screened_by', 'screened_at',
    ]))->toBeTrue();
});

it('rejects a duplicate screening_run_id', function () {
    [$a, $job] = makeApplicantAndJob();
    $runId = (string) Str::ulid();
    insertResult($a, $job, version: 1, runId: $runId);

    expect(fn () => insertResult($a, $job, version: 2, runId: $runId))
        ->toThrow(QueryException::class);
});

it('rejects a duplicate applicant/job/version', function () {
    [$a, $job] = makeApplicantAndJob();
    insertResult($a, $job, version: 1, runId: (string) Str::ulid());

    expect(fn () => insertResult($a, $job, version: 1, runId: (string) Str::ulid()))
        ->toThrow(QueryException::class);
});

it('allows exactly one active override per applicant/job', function () {
    [$a, $job] = makeApplicantAndJob();
    insertOverride($a, $job, activeMarker: 1);

    expect(fn () => insertOverride($a, $job, activeMarker: 1))
        ->toThrow(QueryException::class);
});

it('allows many cleared overrides per applicant/job (NULL-distinct marker)', function () {
    [$a, $job] = makeApplicantAndJob();
    insertOverride($a, $job, activeMarker: null);
    insertOverride($a, $job, activeMarker: null);

    expect(DB::table('applicant_screening_overrides')->count())->toBe(2);
});
