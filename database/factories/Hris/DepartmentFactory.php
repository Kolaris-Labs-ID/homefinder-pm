<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Department',
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'parent_id' => null,
        ];
    }
}

