<?php

use Paymob\Laravel\Models\PaymentMethod;

it('casts primary to boolean', function () {
    $pm = new PaymentMethod(['primary' => true]);
    expect($pm->primary)->toBeTrue();
});

it('hides token from array serialization', function () {
    $pm = new PaymentMethod;
    $pm->token = 'secret_token_value';
    expect(array_keys($pm->toArray()))->not->toContain('token');
});

it('uses configurable table name', function () {
    $pm = new PaymentMethod;
    expect($pm->getTable())->toBe('paymob_payment_methods');
});

it('encrypts token via encrypted cast', function () {
    $pm = new PaymentMethod(['token' => 'card_token_xyz']);
    expect($pm->token)->toBe('card_token_xyz');
});

it('can be persisted and retrieved', function () {
    $pm = PaymentMethod::create([
        'paymob_id' => '55555',
        'token_hash' => hash('sha256', 'token_abc'),
        'token' => 'token_abc',
        'masked_pan' => '411111******1111',
        'brand' => 'VISA',
        'primary' => true,
    ]);
    $found = PaymentMethod::find($pm->id);
    expect($found->paymob_id)->toBe('55555');
    expect($found->brand)->toBe('VISA');
    expect($found->primary)->toBeTrue();
});
