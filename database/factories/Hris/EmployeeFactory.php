<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nik' => $this->faker->unique()->numerify('3204##########'),
            'date_of_birth' => $this->faker->date(),
            'place_of_birth' => $this->faker->city,
            'gender' => $this->faker->randomElement(['male', 'female']),
            'marital_status' => $this->faker->randomElement(['single', 'married']),
            'address' => $this->faker->address,
            'phone' => $this->faker->phoneNumber,
            'hire_date' => now()->subYears(2),
            'employment_status' => 'permanent',
            'is_active' => true,
        ];
    }
}

