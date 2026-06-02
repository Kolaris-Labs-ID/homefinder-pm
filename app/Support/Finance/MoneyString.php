<?php

namespace App\Support\Finance;

final readonly class MoneyString
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Money values must be decimal strings with scale <= 2.');
        }
    }

    public static function from(mixed $value): self
    {
        return new self(bcadd((string) $value, '0', 2));
    }

    public function equals(mixed $other): bool
    {
        return bccomp($this->value, (string) $other, 2) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
