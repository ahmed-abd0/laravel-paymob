<?php

use Paymob\Laravel\Support\Money;

it('converts decimal amounts to cents safely', function () {
    expect(Money::cents('100.25'))->toBe(10025);
    expect(Money::decimal(10025))->toBe('100.25');
});

it('converts integer amounts to cents', function () {
    expect(Money::cents(100))->toBe(10000);
});

it('converts float amounts to cents', function () {
    expect(Money::cents(49.99))->toBe(4999);
});

it('handles zero amount', function () {
    expect(Money::cents(0))->toBe(0);
    expect(Money::cents(0.0))->toBe(0);
});

it('handles large amounts without floating point drift', function () {
    expect(Money::cents('999999.99'))->toBe(99999999);
    expect(Money::decimal(99999999))->toBe('999999.99');
});

it('rounds half up correctly', function () {
    expect(Money::cents('100.255'))->toBe(10026);
    expect(Money::cents('100.254'))->toBe(10025);
});

it('rejects non-numeric values', function () {
    Money::cents('abc');
})->throws(InvalidArgumentException::class);

it('formats cents as two-decimal string', function () {
    expect(Money::decimal(100))->toBe('1.00');
    expect(Money::decimal(1))->toBe('0.01');
    expect(Money::decimal(0))->toBe('0.00');
});
