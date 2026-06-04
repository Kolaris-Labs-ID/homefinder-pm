<?php

namespace Database\Factories\Hris;

use App\Models\Hris\HiringCompany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HiringCompanyFactory extends Factory
{
    protected $model = HiringCompany::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 999999),
            'website' => $this->faker->url(),
            'industry' => $this->faker->randomElement(['Technology', 'Finance', 'Retail', 'Healthcare']),
            'location' => $this->faker->city(),
            'description' => $this->faker->paragraph(),
            'is_internal' => false,
            'status' => HiringCompany::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HiringCompany::STATUS_INACTIVE,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }
}
