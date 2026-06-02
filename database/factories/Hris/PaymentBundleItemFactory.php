<?php

namespace Database\Factories\Hris;

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentBundleItemFactory extends Factory
{
    protected $model = PaymentBundleItem::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(100000, 9000000);

        $snapshot = [
            'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            'payee_name' => $this->faker->company(),
            'amount' => (string) $amount,
            'currency' => 'IDR',
        ];

        return [
            'payment_bundle_id' => PaymentBundle::factory(),
            'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
            'source_id' => $this->faker->unique()->numberBetween(1, 100000),
            'payee_name' => $snapshot['payee_name'],
            'payee_bank_name' => 'BCA',
            'payee_bank_account_last4' => $this->faker->numerify('####'),
            'amount' => $amount,
            'currency' => 'IDR',
            'source_snapshot_json' => $snapshot,
            'source_hash' => hash('sha256', json_encode($snapshot)),
            'status' => PaymentBundleItem::STATUS_ACTIVE,
        ];
    }
}
