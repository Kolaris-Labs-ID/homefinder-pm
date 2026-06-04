<?php

namespace App\Services\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\Employee;
use App\Models\Hris\Interview;
use App\Models\Hris\JobPosting;
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
     * Publish a job posting to the public portal.
     * Requires an internally-published posting linked to a hiring company.
     */
    public function publishJobPostingPublicly(JobPosting $posting): void
    {
        if ($posting->status !== JobPosting::STATUS_PUBLISHED) {
            throw new \Exception('Only internally-published job postings can be made public.');
        }

        if (blank($posting->hiring_company_id)) {
            throw new \Exception('A hiring company is required before publishing to the public portal.');
        }

        $posting->update([
            'is_public' => true,
            'published_public_at' => $posting->published_public_at ?? now(),
            'closed_public_at' => null,
        ]);
    }

    /**
     * Remove a job posting from the public portal.
     */
    public function unpublishJobPostingPublicly(JobPosting $posting): void
    {
        $posting->update([
            'is_public' => false,
            'closed_public_at' => now(),
        ]);
    }

    /**
     * Advance an applicant to the next stage in the pipeline.
     */
    public function advanceApplicant(Applicant $applicant, string $newStatus): void
    {
        $expected = $applicant->getNextStatus();

        if ($expected === null) {
            throw new \Exception("Cannot advance applicant from status: {$applicant->status}");
        }

        if ($newStatus !== $expected) {
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
        if (in_array($applicant->status, Applicant::TERMINAL_STATUSES, true)) {
            throw new \Exception("Cannot reject an applicant with status: {$applicant->status}");
        }

        $applicant->update([
            'status' => Applicant::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Withdraw an applicant (candidate-initiated or HR-recorded).
     */
    public function withdrawApplicant(Applicant $applicant): void
    {
        if (in_array($applicant->status, Applicant::TERMINAL_STATUSES, true)) {
            throw new \Exception("Cannot withdraw an applicant with status: {$applicant->status}");
        }

        $applicant->update([
            'status' => Applicant::STATUS_WITHDRAWN,
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

        if (! in_array($result, $validResults)) {
            throw new \Exception("Invalid interview result: {$result}");
        }

        $interview->update([
            'result' => $result,
            'notes' => $notes,
        ]);
    }
}
