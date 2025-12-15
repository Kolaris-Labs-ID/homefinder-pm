<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Department;
use App\Models\Hris\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(JobPosting::getStatuses());
        $publishedAt = null;
        $closedAt = null;

        if ($status === JobPosting::STATUS_PUBLISHED) {
            $publishedAt = $this->faker->dateTimeBetween('-3 months', 'now');
        } elseif ($status === JobPosting::STATUS_CLOSED) {
            $publishedAt = $this->faker->dateTimeBetween('-6 months', '-1 month');
            $closedAt = $this->faker->dateTimeBetween($publishedAt, 'now');
        }

        return [
            'title' => $this->faker->jobTitle(),
            'department_id' => Department::factory(),
            'location' => $this->faker->city(),
            'employment_type' => $this->faker->randomElement(JobPosting::getEmploymentTypes()),
            'description' => $this->faker->paragraphs(3, true),
            'requirements' => $this->faker->paragraphs(2, true),
            'status' => $status,
            'published_at' => $publishedAt,
            'closed_at' => $closedAt,
        ];
    }

    /**
     * Set status to draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPosting::STATUS_DRAFT,
            'published_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Set status to published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPosting::STATUS_PUBLISHED,
            'published_at' => now(),
            'closed_at' => null,
        ]);
    }

    /**
     * Set status to closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPosting::STATUS_CLOSED,
            'published_at' => now()->subMonth(),
            'closed_at' => now(),
        ]);
    }

    /**
     * Set a specific employment type.
     */
    public function employmentType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_type' => $type,
        ]);
    }

    /**
     * Set full-time employment type.
     */
    public function fullTime(): static
    {
        return $this->employmentType(JobPosting::EMPLOYMENT_TYPE_FULL_TIME);
    }

    /**
     * Set part-time employment type.
     */
    public function partTime(): static
    {
        return $this->employmentType(JobPosting::EMPLOYMENT_TYPE_PART_TIME);
    }

    /**
     * Set contract employment type.
     */
    public function contract(): static
    {
        return $this->employmentType(JobPosting::EMPLOYMENT_TYPE_CONTRACT);
    }

    /**
     * Set intern employment type.
     */
    public function intern(): static
    {
        return $this->employmentType(JobPosting::EMPLOYMENT_TYPE_INTERN);
    }

    /**
     * Set a specific department.
     */
    public function forDepartment(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department_id' => $department->id,
        ]);
    }

    /**
     * Set a specific location.
     */
    public function inLocation(string $location): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => $location,
        ]);
    }
}
