<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Epic>
 */
class EpicFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'start_date' => fake()->date(),
            'end_date' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}

