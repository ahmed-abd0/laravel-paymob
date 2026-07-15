<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Resources\SubscriptionPlans;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('creates a plan via POST with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response(['id' => 88888, 'name' => 'Plan'], 201),
    ]);
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 49900, integration: 465538);
    $response = app(SubscriptionPlans::class)->create($data);
    expect($response->successful())->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/api/acceptance/subscription-plans')
            && $request->data()['name'] === 'Plan';
    });
});

it('lists plans via GET with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([['id' => 1]], 200),
    ]);
    $response = app(SubscriptionPlans::class)->all();
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'GET');
});

it('updates a plan via PUT with bearer auth', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*' => Http::response(['id' => 88888], 200),
    ]);
    $response = app(SubscriptionPlans::class)->update(88888, ['amount_cents' => 59900]);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PUT');
});

it('suspends a plan via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*/suspend' => Http::response(['id' => 88888], 200),
    ]);
    $response = app(SubscriptionPlans::class)->suspend(88888);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'suspend'));
});

it('resumes a plan via POST', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*/resume' => Http::response(['id' => 88888], 200),
    ]);
    $response = app(SubscriptionPlans::class)->resume(88888);
    expect($response->successful())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'resume'));
});
