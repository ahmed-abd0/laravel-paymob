<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Resources\Transactions;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('retrieves transaction by ID via GET with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/transactions/*' => Http::response(['id' => 98765], 200),
    ]);
    $response = app(Transactions::class)->find(98765);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'GET');
});

it('retrieves transaction by order ID via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/ecommerce/orders/transaction_inquiry' => Http::response(['id' => 98765], 200),
    ]);
    $response = app(Transactions::class)->byOrder(orderId: 12345);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        $data = json_decode($request->body(), true);

        return str_contains($request->url(), 'transaction_inquiry') && ($data['order_id'] ?? null) === 12345;
    });
});

it('retrieves transaction by merchant order ID', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/ecommerce/orders/transaction_inquiry' => Http::response(['id' => 98765], 200),
    ]);
    $response = app(Transactions::class)->byOrder(merchantOrderId: 'ORDER-001');
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        $data = json_decode($request->body(), true);

        return str_contains($request->url(), 'transaction_inquiry') && ($data['merchant_order_id'] ?? null) === 'ORDER-001';
    });
});

it('throws InvalidArgumentException when no order identifier is provided', function () {
    app(Transactions::class)->byOrder();
})->throws(InvalidArgumentException::class);
