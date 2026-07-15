<?php

use Paymob\Laravel\Data\BillingData;

it('serializes valid billing data to array', function () {
    $billing = new BillingData(
        firstName: 'Ahmed',
        lastName: 'Abdo',
        email: 'ahmed@example.com',
        phoneNumber: '+201000000000'
    );
    $array = $billing->toArray();
    expect($array)->toHaveKeys([
        'first_name', 'last_name', 'email', 'phone_number',
        'apartment', 'floor', 'street', 'building', 'city', 'country', 'state', 'postal_code',
    ]);
    expect($array['first_name'])->toBe('Ahmed');
    expect($array['email'])->toBe('ahmed@example.com');
    expect($array['country'])->toBe('EG');
});

it('creates billing data from snake_case array', function () {
    $billing = BillingData::fromArray([
        'first_name' => 'Sara',
        'last_name' => 'Ali',
        'email' => 'sara@example.com',
        'phone_number' => '+201111111111',
        'city' => 'Cairo',
    ]);
    expect($billing->firstName)->toBe('Sara');
    expect($billing->city)->toBe('Cairo');
});

it('creates billing data from camelCase array', function () {
    $billing = BillingData::fromArray([
        'firstName' => 'Omar',
        'lastName' => 'Hassan',
        'email' => 'omar@example.com',
        'phoneNumber' => '+201222222222',
    ]);
    expect($billing->firstName)->toBe('Omar');
    expect($billing->phoneNumber)->toBe('+201222222222');
});

it('uses default values for optional fields', function () {
    $billing = new BillingData(firstName: 'A', lastName: 'B', email: 'a@b.com', phoneNumber: '123');
    $array = $billing->toArray();
    expect($array['apartment'])->toBe('NA');
    expect($array['floor'])->toBe('NA');
    expect($array['city'])->toBe('NA');
    expect($array['country'])->toBe('EG');
    expect($array['state'])->toBe('NA');
    expect($array['postal_code'])->toBe('NA');
});

it('rejects empty first name', function () {
    $billing = new BillingData(firstName: '', lastName: 'B', email: 'a@b.com', phoneNumber: '123');
    $billing->toArray();
})->throws(InvalidArgumentException::class);

it('rejects empty last name', function () {
    $billing = new BillingData(firstName: 'A', lastName: '', email: 'a@b.com', phoneNumber: '123');
    $billing->toArray();
})->throws(InvalidArgumentException::class);

it('rejects empty phone number', function () {
    $billing = new BillingData(firstName: 'A', lastName: 'B', email: 'a@b.com', phoneNumber: '');
    $billing->toArray();
})->throws(InvalidArgumentException::class);

it('rejects invalid email', function () {
    $billing = new BillingData(firstName: 'A', lastName: 'B', email: 'not-an-email', phoneNumber: '123');
    $billing->toArray();
})->throws(InvalidArgumentException::class);
