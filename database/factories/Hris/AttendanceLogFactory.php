<?php

namespace Database\Factories\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        $checkInTime = $this->faker->dateTimeBetween('today 07:00', 'today 10:00');
        $checkOutTime = $this->faker->dateTimeBetween('today 16:00', 'today 19:00');

        // Generate realistic GPS coordinates (Jakarta area)
        $baseLat = -6.2088;
        $baseLng = 106.8456;

        return [
            'employee_id' => Employee::factory(),
            'date' => now()->toDateString(),
            'mode' => $this->faker->randomElement(AttendanceLog::getModes()),
            'check_in_time' => $checkInTime,
            'check_in_latitude' => $baseLat + $this->faker->randomFloat(6, -0.05, 0.05),
            'check_in_longitude' => $baseLng + $this->faker->randomFloat(6, -0.05, 0.05),
            'check_in_address' => $this->faker->address,
            'check_in_photo_path' => null,
            'check_out_time' => $checkOutTime,
            'check_out_latitude' => $baseLat + $this->faker->randomFloat(6, -0.05, 0.05),
            'check_out_longitude' => $baseLng + $this->faker->randomFloat(6, -0.05, 0.05),
            'check_out_address' => $this->faker->address,
            'check_out_photo_path' => null,
            'source' => $this->faker->randomElement([AttendanceLog::SOURCE_WEB, AttendanceLog::SOURCE_MOBILE, AttendanceLog::SOURCE_BIOMETRIC]),
            'status' => AttendanceLog::STATUS_PRESENT,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the attendance is for office mode.
     */
    public function office(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => AttendanceLog::MODE_OFFICE,
        ]);
    }

    /**
     * Indicate that the attendance is for WFH mode.
     */
    public function wfh(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => AttendanceLog::MODE_WFH,
        ]);
    }

    /**
     * Indicate that the employee has only checked in (no check-out).
     */
    public function checkedInOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_out_time' => null,
            'check_out_latitude' => null,
            'check_out_longitude' => null,
            'check_out_address' => null,
            'check_out_photo_path' => null,
        ]);
    }

    /**
     * Indicate that the attendance is late.
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_time' => $this->faker->dateTimeBetween('today 09:30', 'today 11:00'),
            'status' => AttendanceLog::STATUS_LATE,
        ]);
    }

    /**
     * Indicate that the attendance is absent.
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_time' => null,
            'check_in_latitude' => null,
            'check_in_longitude' => null,
            'check_in_address' => null,
            'check_out_time' => null,
            'check_out_latitude' => null,
            'check_out_longitude' => null,
            'check_out_address' => null,
            'status' => AttendanceLog::STATUS_ABSENT,
        ]);
    }

    /**
     * Set specific GPS coordinates for check-in.
     */
    public function withCheckInCoordinates(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in_latitude' => $latitude,
            'check_in_longitude' => $longitude,
        ]);
    }

    /**
     * Set specific GPS coordinates for check-out.
     */
    public function withCheckOutCoordinates(float $latitude, float $longitude): static
    {
        return $this->state(fn (array $attributes) => [
            'check_out_latitude' => $latitude,
            'check_out_longitude' => $longitude,
        ]);
    }

    /**
     * Set a specific date for the attendance.
     */
    public function forDate($date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
