<?php

use App\Support\Money;

it('multiplies a decimal amount by an integer day count exactly', function () {
    expect(Money::mul('6000000.00', 8))->toBe('48000000.00');
    expect(Money::mul('7500000.00', 15))->toBe('112500000.00');
});

it('adds amounts at 2-decimal scale', function () {
    expect(Money::add('48000000.00', '112500000.00'))->toBe('160500000.00');
});

it('divides a numerator by an integer divisor and rounds once, half up', function () {
    expect(Money::divideRound('160500000.00', 23))->toBe('6978260.87');
    expect(Money::divideRound('6000000.00', 1))->toBe('6000000.00');
    expect(Money::divideRound('1.00', 8))->toBe('0.13'); // 0.125 -> 0.13
});

it('returns 0.00 for a zero divisor guard', function () {
    expect(Money::divideRound('6000000.00', 0))->toBe('0.00');
});
