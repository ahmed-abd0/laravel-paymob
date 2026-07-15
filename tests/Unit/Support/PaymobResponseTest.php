<?php

use Paymob\Laravel\Support\PaymobResponse;

it('indicates successful responses in 200-299 range', function () {
    expect((new PaymobResponse(200, []))->successful())->toBeTrue();
    expect((new PaymobResponse(201, []))->successful())->toBeTrue();
    expect((new PaymobResponse(299, []))->successful())->toBeTrue();
});

it('indicates failed responses outside 200-299 range', function () {
    expect((new PaymobResponse(400, []))->successful())->toBeFalse();
    expect((new PaymobResponse(422, []))->successful())->toBeFalse();
    expect((new PaymobResponse(500, []))->successful())->toBeFalse();
});

it('retrieves nested values with dot notation', function () {
    $response = new PaymobResponse(200, ['payment_keys' => [['key' => 'secret123']]]);
    expect($response->get('payment_keys.0.key'))->toBe('secret123');
    expect($response->get('missing.key', 'default'))->toBe('default');
});

it('serializes to array', function () {
    $data = ['id' => 1, 'name' => 'test'];
    $response = new PaymobResponse(200, $data);
    expect($response->toArray())->toBe($data);
    expect(json_decode(json_encode($response), true))->toBe($data);
});

it('supports array access for reading', function () {
    $response = new PaymobResponse(200, ['id' => 42]);
    expect(isset($response['id']))->toBeTrue();
    expect($response['id'])->toBe(42);
    expect(isset($response['missing']))->toBeFalse();
});

it('throws on array write attempts', function () {
    $response = new PaymobResponse(200, []);
    $response['key'] = 'value';
})->throws(LogicException::class);

it('throws on array unset attempts', function () {
    $response = new PaymobResponse(200, ['key' => 'value']);
    unset($response['key']);
})->throws(LogicException::class);

it('exposes status and headers publicly', function () {
    $response = new PaymobResponse(201, [], ['Content-Type' => 'application/json']);
    expect($response->status)->toBe(201);
    expect($response->headers)->toBe(['Content-Type' => 'application/json']);
});
