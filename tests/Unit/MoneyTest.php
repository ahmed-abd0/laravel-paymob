<?php

use Paymob\Laravel\Support\Money;

it('converts decimal amounts to cents safely', function () {
    expect(Money::cents('100.25'))->toBe(10025)
        ->and(Money::decimal(10025))->toBe('100.25');
});
