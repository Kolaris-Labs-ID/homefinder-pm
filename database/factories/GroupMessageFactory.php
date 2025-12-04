<?php

namespace Database\Factories;

use App\Models\ChatGroup;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMessage>
 */
class GroupMessageFactory extends Factory
{
    protected $model = GroupMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => ChatGroup::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'attachment' => null,
        ];
    }

    /**
     * Indicate that the message belongs to a specific group.
     */
    public function forGroup(ChatGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
        ]);
    }

    /**
     * Indicate that the message was sent by a specific user.
     */
    public function sentBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
