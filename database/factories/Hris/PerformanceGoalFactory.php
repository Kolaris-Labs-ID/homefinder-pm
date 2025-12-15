<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceGoalFactory extends Factory
{
    protected $model = PerformanceGoal::class;

    public function definition(): array
    {
        $targetValue = $this->faker->randomFloat(2, 50, 1000);
        $actualValue = $this->faker->optional(0.7)->randomFloat(2, 0, $targetValue * 1.2);
        $weight = $this->faker->randomFloat(2, 5, 30);

        return [
            'employee_id' => Employee::factory(),
            'cycle_id' => PerformanceCycle::factory(),
            'title' => $this->faker->randomElement([
                'Increase sales revenue',
                'Improve customer satisfaction',
                'Complete project deliverables',
                'Reduce operational costs',
                'Enhance team productivity',
                'Develop new skills',
                'Improve code quality',
                'Reduce bug count',
            ]),
            'description' => $this->faker->paragraph(),
            'weight' => $weight,
            'target_value' => $targetValue,
            'actual_value' => $actualValue,
            'score' => $actualValue !== null && $targetValue > 0
                ? ($actualValue / $targetValue) * $weight
                : null,
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
     * Set a specific weight.
     */
    public function withWeight(float $weight): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => $weight,
        ]);
    }

    /**
     * Set goal as not started (no actual value).
     */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_value' => null,
            'score' => null,
        ]);
    }

    /**
     * Set goal as completed (actual >= target).
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $targetValue = $attributes['target_value'] ?? 100;
            $actualValue = $targetValue * $this->faker->randomFloat(2, 1.0, 1.2);
            $weight = $attributes['weight'] ?? 20;

            return [
                'actual_value' => $actualValue,
                'score' => ($actualValue / $targetValue) * $weight,
            ];
        });
    }

    /**
     * Set goal as in progress (actual < target).
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $targetValue = $attributes['target_value'] ?? 100;
            $actualValue = $targetValue * $this->faker->randomFloat(2, 0.3, 0.9);
            $weight = $attributes['weight'] ?? 20;

            return [
                'actual_value' => $actualValue,
                'score' => ($actualValue / $targetValue) * $weight,
            ];
        });
    }

    /**
     * Set specific target and actual values.
     */
    public function withValues(float $targetValue, ?float $actualValue = null): static
    {
        return $this->state(function (array $attributes) use ($targetValue, $actualValue) {
            $weight = $attributes['weight'] ?? 20;
            $score = $actualValue !== null && $targetValue > 0
                ? ($actualValue / $targetValue) * $weight
                : null;

            return [
                'target_value' => $targetValue,
                'actual_value' => $actualValue,
                'score' => $score,
            ];
        });
    }
}
