<?php

namespace Database\Factories\Hris;

use App\Models\Hris\EmployeeMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeMovementFactory extends Factory
{
    protected $model = EmployeeMovement::class;

    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Hris\Employee::factory(),
            'movement_type' => $this->faker->randomElement(['promotion', 'demotion', 'transfer', 'salary_increase', 'salary_decrease']),
            'from_department_id' => \App\Models\Hris\Department::factory(),
            'to_department_id' => \App\Models\Hris\Department::factory(),
            'from_position_id' => \App\Models\Hris\Position::factory(),
            'to_position_id' => \App\Models\Hris\Position::factory(),
            'effective_date' => $this->faker->date(),
            'from_salary' => $this->faker->optional()->numberBetween(5000000, 50000000),
            'to_salary' => $this->faker->optional()->numberBetween(5000000, 50000000),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}

