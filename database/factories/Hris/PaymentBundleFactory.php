<?php

namespace Database\Factories\Hris;

use App\Models\Hris\PaymentBundle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentBundleFactory extends Factory
{
    protected $model = PaymentBundle::class;

    public function definition(): array
    {
        return [
            'bundle_number' => 'PB-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'title' => $this->faker->optional()->sentence(3),
            'status' => PaymentBundle::STATUS_DRAFT,
            'bundle_version' => 1,
            'total_amount' => 0,
            'currency' => 'IDR',
            'created_by' => User::factory(),
            'public_verification_code' => Str::random(48),
        ];
    }
}
