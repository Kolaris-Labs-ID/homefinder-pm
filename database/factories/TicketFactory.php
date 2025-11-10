<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'ticket_status_id' => TicketStatus::factory(),
            'priority_id' => TicketPriority::factory(),
            'name' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'start_date' => fake()->date(),
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'uuid' => null, // Will be generated automatically
            'epic_id' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the ticket has no due date.
     */
    public function withoutDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => null,
        ]);
    }

    /**
     * Indicate that the ticket is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day')->format('Y-m-d'),
        ]);
    }

    /**
     * Indicate that the ticket is nearing overdue.
     */
    public function nearingOverdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->addDays(2)->format('Y-m-d'),
        ]);
    }
}

