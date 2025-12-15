<?php

namespace App\Services\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\Employee;
use App\Models\Hris\Interview;
use App\Models\Hris\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class RecruitmentService
{
    /**
     * Publish a job posting.
     */
    public function publishJobPosting(JobPosting $posting): void
    {
        if ($posting->status !== JobPosting::STATUS_DRAFT) {
            throw new \Exception("Only draft job postings can be published. Current status: {$posting->status}");
        }

        $posting->update([
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    /**
     * Close a job posting.
     */
    public function closeJobPosting(JobPosting $posting): void
    {
        if ($posting->status !== JobPosting::STATUS_PUBLISHED) {
            throw new \Exception("Only published job postings can be closed. Current status: {$posting->status}");
        }

        $posting->update([
            'status' => JobPosting::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * Advance an applicant to the next stage in the pipeline.
     */
    public function advanceApplicant(Applicant $applicant, string $newStatus): void
    {
        $validTransitions = [
            Applicant::STATUS_APPLIED => [Applicant::STATUS_SHORTLISTED],
            Applicant::STATUS_SHORTLISTED => [Applicant::STATUS_INTERVIEWED],
            Applicant::STATUS_INTERVIEWED => [Applicant::STATUS_OFFERED],
            Applicant::STATUS_OFFERED => [Applicant::STATUS_HIRED],
        ];

        if (!isset($validTransitions[$applicant->status])) {
            throw new \Exception("Cannot advance applicant from status: {$applicant->status}");
        }

        if (!in_array($newStatus, $validTransitions[$applicant->status])) {
            throw new \Exception("Invalid status transition from {$applicant->status} to {$newStatus}");
        }

        $applicant->update([
            'status' => $newStatus,
        ]);
    }

    /**
     * Reject an applicant with a reason.
     */
    public function rejectApplicant(Applicant $applicant, string $reason): void
    {
        if ($applicant->status === Applicant::STATUS_REJECTED) {
            throw new \Exception("Applicant is already rejected");
        }

        if ($applicant->status === Applicant::STATUS_HIRED) {
            throw new \Exception("Cannot reject a hired applicant");
        }

        $applicant->update([
            'status' => Applicant::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Hire an applicant and link to employee record.
     */
    public function hireApplicant(Applicant $applicant, Employee $employee): void
    {
        if ($applicant->status !== Applicant::STATUS_OFFERED) {
            throw new \Exception("Only applicants with 'offered' status can be hired. Current status: {$applicant->status}");
        }

        $applicant->update([
            'status' => Applicant::STATUS_HIRED,
            'hired_employee_id' => $employee->id,
        ]);
    }

    /**
     * Schedule an interview for an applicant.
     */
    public function scheduleInterview(Applicant $applicant, array $data): Interview
    {
        $interview = Interview::create([
            'applicant_id' => $applicant->id,
            'interviewer_id' => $data['interviewer_id'],
            'scheduled_at' => $data['scheduled_at'],
            'location' => $data['location'] ?? null,
            'interview_type' => $data['interview_type'] ?? Interview::TYPE_ONSITE,
            'result' => Interview::RESULT_PENDING,
        ]);

        // TODO: Send notification to interviewer
        // This would integrate with the existing notification system
        // Notification::send($interviewer, new InterviewScheduledNotification($interview));

        return $interview;
    }

    /**
     * Record the result of an interview.
     */
    public function recordInterviewResult(Interview $interview, string $result, ?string $notes = null): void
    {
        $validResults = [Interview::RESULT_PASS, Interview::RESULT_FAIL];

        if (!in_array($result, $validResults)) {
            throw new \Exception("Invalid interview result: {$result}");
        }

        $interview->update([
            'result' => $result,
            'notes' => $notes,
        ]);
    }
}
