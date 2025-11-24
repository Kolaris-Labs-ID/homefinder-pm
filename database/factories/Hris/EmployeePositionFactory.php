<?php

namespace Database\Factories\Hris;

use App\Models\Hris\EmployeePosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeePositionFactory extends Factory
{
    protected $model = EmployeePosition::class;

    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Hris\Employee::factory(),
            'department_id' => \App\Models\Hris\Department::factory(),
            'position_id' => \App\Models\Hris\Position::factory(),
            'manager_id' => null,
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}

