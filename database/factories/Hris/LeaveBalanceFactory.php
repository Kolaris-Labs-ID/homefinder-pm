<?php

namespace Database\Factories\Hris;

use App\Models\Hris\LeaveBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    public function definition(): array
    {
        return [
            'employee_id' => \App\Models\Hris\Employee::factory(),
            'leave_type_id' => \App\Models\Hris\LeaveType::factory(),
            'year' => now()->year,
            'starting_balance' => $this->faker->numberBetween(10, 20),
            'used_days' => 0,
            'remaining_days' => fn (array $attributes) => $attributes['starting_balance'],
        ];
    }
}

