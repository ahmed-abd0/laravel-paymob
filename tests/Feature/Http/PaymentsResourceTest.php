<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Resources\Payments;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('sends refund request via POST with secret auth', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    $response = app(Payments::class)->refund('tx_123', 5000);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/acceptance/void_refund/refund')
            && $request->data()['transaction_id'] === 'tx_123'
            && $request->data()['amount_cents'] === 5000;
    });
});

it('sends void request via POST with secret auth', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    $response = app(Payments::class)->void('tx_456');
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/acceptance/void_refund/void')
            && $request->data()['transaction_id'] === 'tx_456';
    });
});

it('sends capture request via POST with secret auth', function () {
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    $response = app(Payments::class)->capture('tx_789', 10000);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/acceptance/capture')
            && $request->data()['transaction_id'] === 'tx_789'
            && $request->data()['amount_cents'] === 10000;
    });
});

it('throws ApiException on refund failure', function () {
    Http::fake(['*' => Http::response(['message' => 'Transaction not found'], 404)]);
    app(Payments::class)->refund('tx_bad', 5000);
})->throws(ApiException::class);
