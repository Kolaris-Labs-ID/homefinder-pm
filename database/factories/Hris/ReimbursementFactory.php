<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hris\Reimbursement>
 */
class ReimbursementFactory extends Factory
{
    protected $model = Reimbursement::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => $this->faker->randomElement(Reimbursement::getTypes()),
            'amount' => $this->faker->randomFloat(2, 50000, 5000000),
            'description' => $this->faker->sentence(),
            'receipt_file_path' => null,
            'status' => Reimbursement::STATUS_PENDING,
            'rejection_reason' => null,
            'paid_at' => null,
            'paid_by' => null,
            'metadata' => null,
        ];
    }

    /**
     * Set the reimbursement as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reimbursement::STATUS_PENDING,
        ]);
    }

    /**
     * Set the reimbursement as approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reimbursement::STATUS_APPROVED,
        ]);
    }

    /**
     * Set the reimbursement as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reimbursement::STATUS_REJECTED,
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Set the reimbursement as paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reimbursement::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => User::factory(),
        ]);
    }

    /**
     * Set the reimbursement type to medical.
     */
    public function medical(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Reimbursement::TYPE_MEDICAL,
        ]);
    }

    /**
     * Set the reimbursement type to glasses.
     */
    public function glasses(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Reimbursement::TYPE_GLASSES,
        ]);
    }

    /**
     * Set the reimbursement type to transport.
     */
    public function transport(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Reimbursement::TYPE_TRANSPORT,
        ]);
    }
}
