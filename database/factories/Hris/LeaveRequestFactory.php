<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $start = now()->addWeek();
        $end = (clone $start)->addDays(2);

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => $start->diffInDays($end) + 1,
            'status' => LeaveRequest::STATUS_PENDING,
        ];
    }
}

