<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceReviewFactory extends Factory
{
    protected $model = PerformanceReview::class;

    public function definition(): array
    {
        $isSubmitted = $this->faker->boolean(70);

        return [
            'employee_id' => Employee::factory(),
            'cycle_id' => PerformanceCycle::factory(),
            'reviewer_id' => User::factory(),
            'overall_score' => $this->faker->randomFloat(2, 0, 100),
            'comments' => $this->faker->paragraphs(2, true),
            'submitted_at' => $isSubmitted ? $this->faker->dateTimeBetween('-3 months', 'now') : null,
        ];
    }

    /**
     * Set a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set a specific cycle.
     */
    public function forCycle(PerformanceCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'cycle_id' => $cycle->id,
        ]);
    }

    /**
     * Set a specific reviewer.
     */
    public function byReviewer(User $reviewer): static
    {
        return $this->state(fn (array $attributes) => [
            'reviewer_id' => $reviewer->id,
        ]);
    }

    /**
     * Set the review as submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set the review as pending (not submitted).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => null,
        ]);
    }

    /**
     * Set a specific overall score.
     */
    public function withScore(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $score,
        ]);
    }

    /**
     * Set excellent performance (score >= 90).
     */
    public function excellent(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->randomFloat(2, 90, 100),
            'comments' => 'Exceptional performance. Exceeded all expectations.',
        ]);
    }

    /**
     * Set good performance (score 70-89).
     */
    public function good(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->randomFloat(2, 70, 89.99),
            'comments' => 'Good performance. Met most expectations.',
        ]);
    }

    /**
     * Set needs improvement (score < 70).
     */
    public function needsImprovement(): static
    {
        return $this->state(fn (array $attributes) => [
            'overall_score' => $this->faker->randomFloat(2, 0, 69.99),
            'comments' => 'Performance needs improvement. Development plan recommended.',
        ]);
    }
}
