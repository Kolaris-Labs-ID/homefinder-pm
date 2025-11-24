<?php

namespace Database\Factories\Hris;

use App\Models\Hris\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Hris\Employee::factory(),
            'type' => $this->faker->randomElement(['contract', 'ktp', 'npwp', 'ijazah', 'sp', 'other']),
            'name' => $this->faker->sentence(3),
            'file_path' => 'hris/documents/' . $this->faker->uuid() . '.pdf',
            'issued_date' => $this->faker->optional()->date(),
            'expiry_date' => $this->faker->optional()->date(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}

