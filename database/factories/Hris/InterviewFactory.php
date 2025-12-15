<?php

namespace Database\Factories\Hris;

use App\Models\Hris\Applicant;
use App\Models\Hris\Interview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'interviewer_id' => User::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'location' => $this->faker->randomElement([
                'Conference Room A',
                'Meeting Room 1',
                'Office Building 2, Floor 3',
                'Zoom Meeting',
                'Google Meet',
                null,
            ]),
            'interview_type' => Interview::TYPE_ONSITE,
            'result' => Interview::RESULT_PENDING,
            'notes' => null,
        ];
    }

    /**
     * Set interview type to phone.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes) => [
            'interview_type' => Interview::TYPE_PHONE,
            'location' => $this->faker->phoneNumber(),
        ]);
    }

    /**
     * Set interview type to video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'interview_type' => Interview::TYPE_VIDEO,
            'location' => $this->faker->randomElement([
                'Zoom Meeting',
                'Google Meet',
                'Microsoft Teams',
                'https://meet.google.com/' . $this->faker->uuid(),
            ]),
        ]);
    }

    /**
     * Set interview type to onsite.
     */
    public function onsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'interview_type' => Interview::TYPE_ONSITE,
            'location' => $this->faker->randomElement([
                'Conference Room A',
                'Meeting Room 1',
                'Office Building 2, Floor 3',
                'Main Office',
            ]),
        ]);
    }

    /**
     * Set result to pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => Interview::RESULT_PENDING,
            'notes' => null,
        ]);
    }

    /**
     * Set result to pass with notes.
     */
    public function passed(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => Interview::RESULT_PASS,
            'notes' => $notes ?? $this->faker->paragraph(),
        ]);
    }

    /**
     * Set result to fail with notes.
     */
    public function failed(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => Interview::RESULT_FAIL,
            'notes' => $notes ?? $this->faker->paragraph(),
        ]);
    }

    /**
     * Set a specific applicant.
     */
    public function forApplicant(Applicant $applicant): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_id' => $applicant->id,
        ]);
    }

    /**
     * Set a specific interviewer.
     */
    public function withInterviewer(User $interviewer): static
    {
        return $this->state(fn (array $attributes) => [
            'interviewer_id' => $interviewer->id,
        ]);
    }

    /**
     * Set a specific scheduled date.
     */
    public function scheduledAt($date): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $date,
        ]);
    }

    /**
     * Set a random result.
     */
    public function randomResult(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => $this->faker->randomElement(Interview::getResults()),
            'notes' => $this->faker->optional(0.7)->paragraph(),
        ]);
    }

    /**
     * Set a random interview type.
     */
    public function randomType(): static
    {
        return $this->state(fn (array $attributes) => [
            'interview_type' => $this->faker->randomElement(Interview::getTypes()),
        ]);
    }

    /**
     * Schedule in the past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Schedule in the future.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    /**
     * Create without location.
     */
    public function withoutLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => null,
        ]);
    }

    /**
     * Create with notes.
     */
    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => $notes ?? $this->faker->paragraph(),
        ]);
    }
}
