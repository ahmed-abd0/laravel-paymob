<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Resources\QuickLinks;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('creates a quick link via multipart POST with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/ecommerce/payment-links' => Http::response(['client_url' => 'https://pay.test/link'], 201),
    ]);
    $response = app(QuickLinks::class)->create([
        'amount_cents' => 10000,
        'payment_methods' => 465537,
        'is_live' => false,
    ]);
    expect($response->successful())->toBeTrue();
    expect($response->get('client_url'))->toBe('https://pay.test/link');
});

it('cancels a quick link via multipart POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/ecommerce/payment-links/cancel' => Http::response(['status' => 'cancelled'], 200),
    ]);
    $response = app(QuickLinks::class)->cancel(12345);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'payment-links/cancel');
    });
});

it('converts boolean values to strings in multipart payload', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/ecommerce/payment-links' => function ($request) {
            $multipart = $request->toPsrRequest()->getBody()->getContents();
            expect($multipart)->toContain('true');

            return Http::response(['client_url' => 'https://pay.test'], 201);
        },
    ]);
    app(QuickLinks::class)->create([
        'amount_cents' => 1000,
        'is_live' => true,
    ]);
});
