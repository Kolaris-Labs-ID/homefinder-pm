<?php

namespace App\Enums;

/**
 * Signature slots for a Payment Bundle — defines sequential signing order and required permission.
 */
enum PaymentBundleSignatureSlot: string
{
    case FINANCE_SIGNER_1 = 'finance_signer_1';
    case FINANCE_SIGNER_2 = 'finance_signer_2';
    case EXECUTIVE_SIGNER = 'executive_signer';

    public function requiredPermission(): string
    {
        return match ($this) {
            self::FINANCE_SIGNER_1, self::FINANCE_SIGNER_2 => 'payment_bundles.sign.finance',
            self::EXECUTIVE_SIGNER => 'payment_bundles.sign.executive',
        };
    }

    public function isFinance(): bool
    {
        return match ($this) {
            self::FINANCE_SIGNER_1, self::FINANCE_SIGNER_2 => true,
            self::EXECUTIVE_SIGNER => false,
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::FINANCE_SIGNER_1 => 1,
            self::FINANCE_SIGNER_2 => 2,
            self::EXECUTIVE_SIGNER => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FINANCE_SIGNER_1 => 'Finance Signer 1',
            self::FINANCE_SIGNER_2 => 'Finance Signer 2',
            self::EXECUTIVE_SIGNER => 'Executive Signer',
        };
    }

    public static function ordered(): array
    {
        return [self::FINANCE_SIGNER_1, self::FINANCE_SIGNER_2, self::EXECUTIVE_SIGNER];
    }
}
