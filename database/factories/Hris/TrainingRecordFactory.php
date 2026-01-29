<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainingRecordFactory extends Factory
{
    protected $model = TrainingRecord::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(TrainingRecord::getStatuses());
        $completionDate = $status === TrainingRecord::STATUS_COMPLETED
            ? $this->faker->dateTimeBetween('-1 year', 'now')
            : null;
        $hasExpiry = $this->faker->boolean(60);

        return [
            'employee_id' => Employee::factory(),
            'training_name' => $this->faker->randomElement([
                'First Aid Certification',
                'Fire Safety Training',
                'Leadership Development',
                'Project Management Fundamentals',
                'Data Privacy & Security',
                'Communication Skills',
                'Time Management',
                'Workplace Safety',
                'Customer Service Excellence',
                'Technical Writing',
                'Agile Methodology',
                'Conflict Resolution',
            ]),
            'provider' => $this->faker->randomElement([
                'Internal HR',
                'LinkedIn Learning',
                'Coursera',
                'Udemy Business',
                'Red Cross',
                'PMI',
                'External Consultant',
                null,
            ]),
            'description' => $this->faker->paragraph(),
            'completion_date' => $completionDate,
            'expiry_date' => $hasExpiry && $completionDate
                ? $this->faker->dateTimeBetween($completionDate, '+3 years')
                : null,
            'certificate_file_path' => $status === TrainingRecord::STATUS_COMPLETED && $this->faker->boolean(70)
                ? 'certificates/' . $this->faker->uuid() . '.pdf'
                : null,
            'status' => $status,
            'training_type' => $this->faker->randomElement([
                TrainingRecord::TRAINING_TYPE_FREE,
                TrainingRecord::TRAINING_TYPE_PAID,
            ]),
            'pgp_points' => null,
            'approved_at' => null,
        ];
    }

    /**
     * Set status to planned.
     */
    public function planned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_PLANNED,
            'completion_date' => null,
            'certificate_file_path' => null,
        ]);
    }

    /**
     * Set status to in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_IN_PROGRESS,
            'completion_date' => null,
            'certificate_file_path' => null,
        ]);
    }

    /**
     * Set status to completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_COMPLETED,
            'completion_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Set training as expiring soon (within 30 days).
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_COMPLETED,
            'completion_date' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
            'expiry_date' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * Set training as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_COMPLETED,
            'completion_date' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
            'expiry_date' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Set training with no expiry date.
     */
    public function noExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => null,
        ]);
    }

    /**
     * Set training with certificate.
     */
    public function withCertificate(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRecord::STATUS_COMPLETED,
            'completion_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'certificate_file_path' => 'certificates/' . $this->faker->uuid() . '.pdf',
        ]);
    }

    /**
     * Set specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * Set specific expiry date.
     */
    public function expiresOn(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => $date,
        ]);
    }

    /**
     * Set training type to free.
     */
    public function freeTraining(): static
    {
        return $this->state(fn (array $attributes) => [
            'training_type' => TrainingRecord::TRAINING_TYPE_FREE,
        ]);
    }

    /**
     * Set training type to paid.
     */
    public function paidTraining(): static
    {
        return $this->state(fn (array $attributes) => [
            'training_type' => TrainingRecord::TRAINING_TYPE_PAID,
        ]);
    }

    /**
     * Set training as approved for PGP with specified points.
     */
    public function approvedForPgp(int $points = null, \DateTimeInterface $approvedAt = null): static
    {
        return $this->state(function (array $attributes) use ($points, $approvedAt) {
            $trainingType = $attributes['training_type'] ?? TrainingRecord::TRAINING_TYPE_FREE;

            if ($points === null) {
                $points = $trainingType === TrainingRecord::TRAINING_TYPE_FREE
                    ? TrainingRecord::PGP_FREE_TRAINING
                    : $this->faker->numberBetween(TrainingRecord::PGP_PAID_MIN, TrainingRecord::PGP_PAID_MAX);
            }

            return [
                'status' => TrainingRecord::STATUS_COMPLETED,
                'pgp_points' => $points,
                'approved_at' => $approvedAt ?? now(),
            ];
        });
    }

    /**
     * Set training as approved for PGP in a specific quarter.
     */
    public function approvedInQuarter(int $quarter, int $year, int $points = null): static
    {
        return $this->state(function (array $attributes) use ($quarter, $year, $points) {
            $trainingType = $attributes['training_type'] ?? TrainingRecord::TRAINING_TYPE_FREE;

            if ($points === null) {
                $points = $trainingType === TrainingRecord::TRAINING_TYPE_FREE
                    ? TrainingRecord::PGP_FREE_TRAINING
                    : $this->faker->numberBetween(TrainingRecord::PGP_PAID_MIN, TrainingRecord::PGP_PAID_MAX);
            }

            $startMonth = ($quarter - 1) * 3 + 1;
            $start = \Carbon\Carbon::create($year, $startMonth, 1);
            $end = $start->copy()->addMonths(3)->subDay();

            return [
                'status' => TrainingRecord::STATUS_COMPLETED,
                'pgp_points' => $points,
                'approved_at' => $this->faker->dateTimeBetween($start, $end),
            ];
        });
    }
}
