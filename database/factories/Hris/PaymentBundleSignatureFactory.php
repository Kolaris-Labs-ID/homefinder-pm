<?php

namespace Database\Factories\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentBundleSignatureFactory extends Factory
{
    protected $model = PaymentBundleSignature::class;

    public function definition(): array
    {
        $slot = PaymentBundleSignatureSlot::FINANCE_SIGNER_1;

        return [
            'payment_bundle_id' => PaymentBundle::factory(),
            'signature_slot' => $slot,
            'required_permission' => $slot->requiredPermission(),
            'status' => PaymentBundleSignature::STATUS_PENDING,
        ];
    }
}
