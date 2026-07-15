<?php

use Paymob\Laravel\Enums\Region;

it('returns correct base URL for each region', function () {
    expect(Region::EGYPT->baseUrl())->toBe('https://accept.paymob.com');
    expect(Region::KSA->baseUrl())->toBe('https://ksa.paymob.com');
    expect(Region::UAE->baseUrl())->toBe('https://uae.paymob.com');
    expect(Region::OMAN->baseUrl())->toBe('https://oman.paymob.com');
});

it('has all expected enum values', function () {
    expect(collect(Region::cases())->pluck('value'))->toEqual(
        collect(['egypt', 'ksa', 'uae', 'oman'])
    );
});
