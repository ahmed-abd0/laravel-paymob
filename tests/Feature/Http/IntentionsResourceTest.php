<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;
use Paymob\Laravel\Resources\Intentions;
use Paymob\Laravel\Support\PaymobResponse;
use Paymob\Laravel\Tests\Fixtures\Fixtures;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('creates an intention via POST with secret auth', function () {
    Http::fake(['*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 201)]);
    $billing = new BillingData(firstName: 'A', lastName: 'B', email: 'a@b.com', phoneNumber: '123');
    $data = new IntentionData(amount: 10000, billingData: $billing, paymentMethods: [465537]);
    $data->items(new Item('Test', 10000));
    $response = app(Intentions::class)->create($data);
    expect($response)->toBeInstanceOf(PaymobResponse::class);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/v1/intention/')
            && $request->hasHeader('Authorization', 'Token sk_test_fake_secret_key')
            && $request->data()['amount'] === 10000;
    });
});

it('updates an intention via PUT', function () {
    Http::fake(['*' => Http::response(['id' => 'intention-001'], 200)]);
    $response = app(Intentions::class)->update('client_secret_123', ['expiration' => 7200]);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), 'client_secret_123');
    });
});

it('retrieves an intention via public endpoint', function () {
    Http::fake(['*/v1/intention/element/*' => Http::response(Fixtures::intentionResponse(), 200)]);
    $response = app(Intentions::class)->retrieve('client_secret_xyz');
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/v1/intention/element/');
    });
});

it('builds checkout URL without making HTTP request', function () {
    $url = app(Intentions::class)->checkoutUrl('test_secret');
    expect($url)->toContain('/unifiedcheckout/?');
    expect($url)->toContain('clientSecret=test_secret');
    expect($url)->toContain('publicKey=');
});

it('builds checkout URL with custom public key', function () {
    $url = app(Intentions::class)->checkoutUrl('secret', 'custom_pk');
    expect($url)->toContain('publicKey=custom_pk');
});

it('returns Checkout object from checkout method', function () {
    Http::fake(['*/v1/intention/element/*' => Http::response(Fixtures::intentionResponse(), 200)]);
    $checkout = app(Intentions::class)->checkout('client_secret_123');
    expect($checkout->url)->toContain('/unifiedcheckout/');
    expect($checkout->clientSecret)->toBe('client_secret_123');
    expect($checkout->subscription)->toBeNull();
});
