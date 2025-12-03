<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'ticket_prefix' => strtoupper(fake()->lexify('???')),
            'start_date' => fake()->date(),
            'end_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'pinned_date' => null,
        ];
    }

    /**
     * Indicate that the project is pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_date' => now(),
        ]);
    }

    /**
     * Indicate that the project has no end date.
     */
    public function withoutEndDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }

    /**
     * Indicate that the project is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
            'archived_by' => \App\Models\User::factory(),
        ]);
    }

    /**
     * Indicate that the project is marked as done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'done_at' => now(),
            'is_auto_done' => false,
        ]);
    }

    /**
     * Indicate that the project is auto-done.
     */
    public function autoDone(): static
    {
        return $this->state(fn (array $attributes) => [
            'done_at' => now(),
            'is_auto_done' => true,
        ]);
    }
}

