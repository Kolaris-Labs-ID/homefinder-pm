<?php

namespace App\Support;

/**
 * Integer-safe money math for payroll. All amounts are decimal STRINGS at the
 * persisted money scale (2). No PHP binary floats — required for salary segmentation.
 */
final class Money
{
    public const SCALE = 2;

    public static function mul(string $amount, int $factor): string
    {
        return bcmul($amount, (string) $factor, self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    /** Divide by an integer divisor and round half-up to SCALE. */
    public static function divideRound(string $numerator, int $divisor): string
    {
        if ($divisor === 0) {
            return bcadd('0', '0', self::SCALE);
        }

        // Divide at extra precision, then round half-up: add half a cent and let
        // bcadd truncate to SCALE. Extra places make the 2nd-decimal rounding exact.
        $quotient = bcdiv($numerator, (string) $divisor, self::SCALE + 4);
        $half = ($quotient[0] === '-' ? '-0.' : '0.').str_repeat('0', self::SCALE).'5';

        return bcadd($quotient, $half, self::SCALE);
    }
}
