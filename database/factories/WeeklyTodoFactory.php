<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeeklyTodo>
 */
class WeeklyTodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get a random date and set it to the start of the week (Monday)
        $randomDate = fake()->dateTimeBetween('-3 months', '+3 months');
        $weekStart = Carbon::parse($randomDate)->startOfWeek(Carbon::MONDAY);
        
        // Deadline can be within the week or up to 2 weeks after
        $deadline = fake()->optional(0.7)->dateTimeBetween(
            $weekStart->copy(),
            $weekStart->copy()->addWeeks(2)
        );
        
        return [
            'user_id' => User::factory(),
            'week_start_date' => $weekStart,
            'deadline' => $deadline ? Carbon::parse($deadline) : null,
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['pending', 'in_progress', 'done']),
        ];
    }
}

