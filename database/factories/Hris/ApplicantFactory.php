<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\Employee;
use App\Models\Hris\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicantFactory extends Factory
{
    protected $model = Applicant::class;

    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'resume_file_path' => 'resumes/'.$this->faker->uuid().'.pdf',
            'cover_letter' => $this->faker->paragraphs(2, true),
            'status' => Applicant::STATUS_APPLIED,
            'rejection_reason' => null,
            'hired_employee_id' => null,
        ];
    }

    /**
     * Set status to applied.
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_APPLIED,
        ]);
    }

    /**
     * Set status to screening.
     */
    public function screening(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_SCREENING,
        ]);
    }

    /**
     * Set status to shortlisted.
     */
    public function shortlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_SHORTLISTED,
        ]);
    }

    /**
     * Set status to interview_scheduled.
     */
    public function interviewScheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_INTERVIEW_SCHEDULED,
        ]);
    }

    /**
     * Set status to withdrawn.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_WITHDRAWN,
        ]);
    }

    /**
     * Set status to interviewed.
     */
    public function interviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_INTERVIEWED,
        ]);
    }

    /**
     * Set status to offered.
     */
    public function offered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_OFFERED,
        ]);
    }

    /**
     * Set status to hired with employee link.
     */
    public function hired(?Employee $employee = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_HIRED,
            'hired_employee_id' => $employee?->id ?? Employee::factory(),
        ]);
    }

    /**
     * Set status to rejected with reason.
     */
    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Applicant::STATUS_REJECTED,
            'rejection_reason' => $reason ?? $this->faker->sentence(),
        ]);
    }

    /**
     * Set a specific job posting.
     */
    public function forJobPosting(JobPosting $jobPosting): static
    {
        return $this->state(fn (array $attributes) => [
            'job_posting_id' => $jobPosting->id,
        ]);
    }

    /**
     * Set a random status from the pipeline.
     */
    public function randomStatus(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(Applicant::getStatuses()),
        ]);
    }

    /**
     * Set a random active status (not rejected or hired).
     */
    public function randomActiveStatus(): static
    {
        $activeStatuses = [
            Applicant::STATUS_APPLIED,
            Applicant::STATUS_SCREENING,
            Applicant::STATUS_SHORTLISTED,
            Applicant::STATUS_INTERVIEW_SCHEDULED,
            Applicant::STATUS_INTERVIEWED,
            Applicant::STATUS_OFFERED,
        ];

        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement($activeStatuses),
        ]);
    }

    /**
     * Create without resume.
     */
    public function withoutResume(): static
    {
        return $this->state(fn (array $attributes) => [
            'resume_file_path' => null,
        ]);
    }

    /**
     * Create without cover letter.
     */
    public function withoutCoverLetter(): static
    {
        return $this->state(fn (array $attributes) => [
            'cover_letter' => null,
        ]);
    }
}
