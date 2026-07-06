<?php

namespace Database\Factories\Hris;

use App\Models\Hris\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('LV???')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'requires_approval' => true,
            // Kept comfortably above the 1-5 day total_days used by LeaveRequestFactory
            // and its callers, so LeaveBalanceService::consume()'s overdraft guard
            // doesn't intermittently reject an otherwise-unrelated test's approval.
            'default_annual_quota' => $this->faker->numberBetween(20, 60),
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ];
    }
}

