<?php

namespace Database\Factories;

use App\Enums\TicketFrequency;
use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketTemplate>
 */
class TicketTemplateFactory extends Factory
{
    protected $model = TicketTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $frequency = fake()->randomElement([TicketFrequency::Weekly, TicketFrequency::Monthly]);

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'project_id' => Project::factory(),
            'frequency' => $frequency,
            'day_value' => $frequency === TicketFrequency::Weekly
                ? fake()->numberBetween(1, 7)
                : fake()->numberBetween(1, 28),
            'lead_time_days' => fake()->numberBetween(0, 14),
            'initial_status_id' => TicketStatus::factory(),
            'priority_id' => TicketPriority::factory(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the template is weekly.
     */
    public function weekly(int $dayOfWeek = null): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TicketFrequency::Weekly,
            'day_value' => $dayOfWeek ?? fake()->numberBetween(1, 7),
        ]);
    }

    /**
     * Indicate that the template is monthly.
     */
    public function monthly(int $dayOfMonth = null): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => TicketFrequency::Monthly,
            'day_value' => $dayOfMonth ?? fake()->numberBetween(1, 28),
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific lead time.
     */
    public function withLeadTime(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'lead_time_days' => $days,
        ]);
    }
}
