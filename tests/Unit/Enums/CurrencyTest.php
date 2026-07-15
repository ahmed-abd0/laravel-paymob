<?php

use Paymob\Laravel\Enums\Currency;

it('contains all expected currencies', function () {
    expect(collect(Currency::cases())->pluck('value'))->toEqual(
        collect(['EGP', 'SAR', 'AED', 'OMR', 'USD'])
    );
});
