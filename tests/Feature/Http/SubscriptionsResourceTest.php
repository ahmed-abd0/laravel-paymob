<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Resources\Subscriptions;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('finds a subscription via GET with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*' => Http::response(['id' => 77777, 'state' => 'activated'], 200),
    ]);
    $response = app(Subscriptions::class)->find(77777);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'GET');
});

it('updates a subscription via PUT with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*' => Http::response(['id' => 77777], 200),
    ]);
    $response = app(Subscriptions::class)->update(77777, ['amount_cents' => 59900]);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PUT');
});

it('suspends a subscription via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/suspend' => Http::response(['id' => 77777], 200),
    ]);
    $response = app(Subscriptions::class)->suspend(77777);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'suspend'));
});

it('resumes a subscription via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/resume' => Http::response(['id' => 77777], 200),
    ]);
    $response = app(Subscriptions::class)->resume(77777);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'resume'));
});

it('cancels a subscription via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/cancel' => Http::response(['id' => 77777], 200),
    ]);
    $response = app(Subscriptions::class)->cancel(77777);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'cancel'));
});

it('retrieves last transaction for a subscription', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/last-transaction' => Http::response(['id' => 98765], 200),
    ]);
    $response = app(Subscriptions::class)->lastTransaction(77777);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'last-transaction'));
});

it('lists subscription transactions', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/transactions' => Http::response([['id' => 1]], 200),
    ]);
    $response = app(Subscriptions::class)->transactions(77777);
    expect($response->successful())->toBeTrue();
});

it('lists subscription cards', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/card-tokens' => Http::response([['token' => 'tok1']], 200),
    ]);
    $response = app(Subscriptions::class)->cards(77777);
    expect($response->successful())->toBeTrue();
});

it('deletes a card from subscription', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/delete-card' => Http::response(['ok' => true], 200),
    ]);
    $response = app(Subscriptions::class)->deleteCard(77777, 12345);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        $data = json_decode($request->body(), true);

        return str_contains($request->url(), 'delete-card') && ($data['card'] ?? null) === 12345;
    });
});

it('changes primary card', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/change-primary-card' => Http::response(['ok' => true], 200),
    ]);
    $response = app(Subscriptions::class)->changePrimaryCard(77777, 12345);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        $data = json_decode($request->body(), true);

        return str_contains($request->url(), 'change-primary-card') && ($data['card'] ?? null) === 12345;
    });
});

it('registers a webhook for a plan', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/register_webhook' => Http::response(['ok' => true], 200),
    ]);
    $response = app(Subscriptions::class)->registerWebhook(88888, 'https://example.com/hook');
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        $data = json_decode($request->body(), true);

        return str_contains($request->url(), 'register_webhook') && ($data['url'] ?? null) === 'https://example.com/hook';
    });
});
