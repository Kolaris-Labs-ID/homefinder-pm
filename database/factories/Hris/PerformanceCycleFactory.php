<?php

namespace Database\Factories\Hris;

use App\Models\Hris\PerformanceCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceCycleFactory extends Factory
{
    protected $model = PerformanceCycle::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', '+3 months');
        $endDate = (clone $startDate)->modify('+' . $this->faker->randomElement([3, 6, 12]) . ' months');

        return [
            'name' => $this->faker->randomElement(['Q1', 'Q2', 'Q3', 'Q4', 'Annual']) . ' ' . $startDate->format('Y') . ' Performance Review',
            'description' => $this->faker->paragraph(),
            'period_start_date' => $startDate,
            'period_end_date' => $endDate,
            'status' => $this->faker->randomElement(PerformanceCycle::getStatuses()),
        ];
    }

    /**
     * Set status to draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycle::STATUS_DRAFT,
        ]);
    }

    /**
     * Set status to active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycle::STATUS_ACTIVE,
        ]);
    }

    /**
     * Set status to closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PerformanceCycle::STATUS_CLOSED,
        ]);
    }

    /**
     * Set a quarterly cycle.
     */
    public function quarterly(): static
    {
        $quarter = $this->faker->randomElement([1, 2, 3, 4]);
        $year = now()->year;
        $startMonth = ($quarter - 1) * 3 + 1;

        return $this->state(fn (array $attributes) => [
            'name' => "Q{$quarter} {$year} Performance Review",
            'period_start_date' => now()->setDate($year, $startMonth, 1)->startOfMonth(),
            'period_end_date' => now()->setDate($year, $startMonth + 2, 1)->endOfMonth(),
        ]);
    }

    /**
     * Set an annual cycle.
     */
    public function annual(): static
    {
        $year = now()->year;

        return $this->state(fn (array $attributes) => [
            'name' => "Annual {$year} Performance Review",
            'period_start_date' => now()->setDate($year, 1, 1)->startOfYear(),
            'period_end_date' => now()->setDate($year, 12, 31)->endOfYear(),
        ]);
    }

    /**
     * Set specific period dates.
     */
    public function forPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start_date' => $startDate,
            'period_end_date' => $endDate,
        ]);
    }
}
