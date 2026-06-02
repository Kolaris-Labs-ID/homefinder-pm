<?php

namespace App\Services\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentAuthorizationSetting;
use App\Models\User;
use RuntimeException;

class PaymentAuthorizationSettingsService
{
    public function current(): PaymentAuthorizationSetting
    {
        return PaymentAuthorizationSetting::current();
    }

    public function validate(?PaymentAuthorizationSetting $settings = null): void
    {
        $s = $settings ?? $this->current();

        if ($s->finance_signer_1_user_id === null) {
            throw new RuntimeException('Finance Signer 1 must be assigned.');
        }

        if ($s->require_two_finance_signatures && $s->finance_signer_2_user_id === null) {
            throw new RuntimeException('Finance Signer 2 must be assigned.');
        }

        if ($s->require_executive_signature && $s->executive_signer_user_id === null) {
            throw new RuntimeException('Executive Signer must be assigned.');
        }

        if (bccomp((string) $s->high_value_threshold, '0', 2) < 0) {
            throw new RuntimeException('The high-value threshold must be zero or greater.');
        }

        $f1 = $s->finance_signer_1_user_id;
        $f2 = $s->finance_signer_2_user_id;
        $exec = $s->executive_signer_user_id;

        if ($f1 !== null && $f2 !== null && (int) $f1 === (int) $f2) {
            throw new RuntimeException('The two finance signers must be different people.');
        }

        if ($exec !== null && (((int) $exec === (int) $f1) || ($f2 !== null && (int) $exec === (int) $f2))) {
            throw new RuntimeException('The executive signer must differ from both finance signers.');
        }

        $slotIds = [
            [PaymentBundleSignatureSlot::FINANCE_SIGNER_1, $f1],
            [PaymentBundleSignatureSlot::FINANCE_SIGNER_2, $f2],
            [PaymentBundleSignatureSlot::EXECUTIVE_SIGNER, $exec],
        ];

        foreach ($slotIds as [$slot, $userId]) {
            if ($userId === null) {
                continue;
            }

            $user = User::findOrFail($userId);

            if (! $user->can($slot->requiredPermission())) {
                throw new RuntimeException(
                    "{$slot->label()} lacks the required permission ({$slot->requiredPermission()})."
                );
            }
        }
    }

    public function isComplete(?PaymentAuthorizationSetting $settings = null): bool
    {
        try {
            $this->validate($settings);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function assignments(?PaymentAuthorizationSetting $settings = null): array
    {
        $s = $settings ?? $this->current();

        $map = [
            PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $s->finance_signer_1_user_id,
            PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $s->finance_signer_2_user_id,
            PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $s->executive_signer_user_id,
        ];

        return array_filter($map, fn ($id) => $id !== null);
    }
}
