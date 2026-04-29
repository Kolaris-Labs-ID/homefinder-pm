<?php

namespace Database\Factories\Hris;

use App\Models\Hris\MasterHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

class MasterHolidayFactory extends Factory
{
    protected $model = MasterHoliday::class;

    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->date(),
            'name' => $this->faker->words(3, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
