<?php

namespace Database\Factories\Hris;

use App\Models\Hris\PayrunBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrunBatchFactory extends Factory
{
    protected $model = PayrunBatch::class;

    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-3 months', 'now');
        $periodEnd = (clone $periodStart)->modify('last day of this month');
        $payDate = (clone $periodEnd)->modify('+5 days');

        return [
            'period_start_date' => $periodStart->format('Y-m-01'),
            'period_end_date' => $periodEnd->format('Y-m-d'),
            'pay_date' => $payDate->format('Y-m-d'),
            'status' => PayrunBatch::STATUS_DRAFT,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Set the batch status to draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrunBatch::STATUS_DRAFT,
        ]);
    }

    /**
     * Set the batch status to calculated.
     */
    public function calculated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrunBatch::STATUS_CALCULATED,
            'calculated_at' => now(),
        ]);
    }

    /**
     * Set the batch status to approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrunBatch::STATUS_APPROVED,
            'calculated_at' => now()->subHour(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    /**
     * Set the batch status to paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrunBatch::STATUS_PAID,
            'calculated_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'approved_by' => User::factory(),
            'paid_at' => now(),
            'paid_by' => User::factory(),
        ]);
    }

    /**
     * Set a specific period.
     */
    public function forPeriod($startDate, $endDate): static
    {
        return $this->state(fn (array $attributes) => [
            'period_start_date' => $startDate,
            'period_end_date' => $endDate,
        ]);
    }

    /**
     * Set a specific pay date.
     */
    public function payOn($date): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_date' => $date,
        ]);
    }

    /**
     * Create a batch for the current month.
     */
    public function currentMonth(): static
    {
        return $this->state(function (array $attributes) {
            $now = now();
            return [
                'period_start_date' => $now->copy()->startOfMonth()->format('Y-m-d'),
                'period_end_date' => $now->copy()->endOfMonth()->format('Y-m-d'),
                'pay_date' => $now->copy()->endOfMonth()->addDays(5)->format('Y-m-d'),
            ];
        });
    }

    /**
     * Create a batch for the previous month.
     */
    public function previousMonth(): static
    {
        return $this->state(function (array $attributes) {
            $lastMonth = now()->subMonth();
            return [
                'period_start_date' => $lastMonth->copy()->startOfMonth()->format('Y-m-d'),
                'period_end_date' => $lastMonth->copy()->endOfMonth()->format('Y-m-d'),
                'pay_date' => $lastMonth->copy()->endOfMonth()->addDays(5)->format('Y-m-d'),
            ];
        });
    }
}
