<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->jobTitle(),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'level' => (string) $this->faker->numberBetween(1, 10),
        ];
    }
}

